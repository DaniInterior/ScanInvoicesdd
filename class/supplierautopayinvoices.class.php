<?php
/**
 * supplier_invoices_autopaid_cb_lcr_pre.php
 *
 * Copyright (c) 2022 Eric Seigne <eric.seigne@cap-rel.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
// include_once __DIR__."/../core/modules/scaninvoices/mod_settings_standard.php";
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/paiementfourn.class.php';
dol_include_once('/scaninvoices/lib/scaninvoices.lib.php');

class Supplierautopayinvoices extends CommonObject
{
	public $module = 'scaninvoices';
	public $element = 'supplierautopayinvoices';
	public $output;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf, $langs;

		$this->db = $db;

		if (empty(getDolGlobalString('MAIN_SHOW_TECHNICAL_ID')) && isset($this->fields['rowid'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (empty($conf->multicompany->enabled) && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}
	}

	/**
	 * Action executed by scheduler
	 * CAN BE A CRON TASK. In such a case, parameters come from the schedule job setup field 'Parameters'
	 * Use public function doScheduledJob($param1, $param2, ...) to get parameters
	 *
	 * @return	int			0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
	 */
	public function doScheduledJob()
	{
		global $conf, $langs, $user, $db;

		$error = 0;
		$this->output = '';
		$this->error = '';

		dol_syslog(__METHOD__, LOG_DEBUG);

		$now = dol_now();

		//Liste des moyens de paiement à prendre en compte
		$paymentmodes = ["'CB'","'PRE'","'LCR'"];

		$langs->load('main');

		$sql = "SELECT f.rowid as id";
		$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn as f";
		$sql .= " WHERE f.fk_statut='1' AND f.paye='0' ";
		$sql .= " AND date_lim_reglement < NOW() ";
		$sql .= " AND fk_mode_reglement IN(SELECT id FROM ".MAIN_DB_PREFIX."c_paiement WHERE code IN(" . implode(',', $paymentmodes) . "))";

		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);
			$this->output .= "We found ".$num." open invoices\n";
			dol_syslog("ScanInvoices::Supplierautopayinvoices::Supplierautopayinvoices We found ".$num." open invoices", LOG_DEBUG);
			if ($num) {
				$i = 0;
				while ($i < $num) {
					$obj = $db->fetch_object($resql);
					$localerror = 0;

					$db->begin();

					$facture = new FactureFournisseur($db);
					$resFac = $facture->fetch($obj->id);
					if ($resFac < 0) {
						continue;
					}

					//if there is a payment on that invoice do nothing, that's a full manualy one
					$amount = $facture->getSommePaiement();
					if (!empty($amount)) {
						dol_syslog("ScanInvoices::Supplierautopayinvoices Facture : " . json_encode($facture), LOG_DEBUG);
						continue;
					}

					$thirdparty = new Societe($db);
					$thirdparty->fetch($facture->socid);
					dol_syslog("ScanInvoices::Supplierautopayinvoices ThirdPart : " . $thirdparty->rowid, LOG_DEBUG);

					$datepaye = dol_now();
					$paiement = new PaiementFourn($db);
					$paiement->datepaye     = $datepaye;
					$paiement->amount       = $facture->total_ttc;
					$paiement->amounts      = [ $facture->id => $facture->total_ttc]; // Array with all payments dispatching with invoice id
					$paiement->paiementid   = $facture->mode_reglement_id;
					$paiement->facid		= $facture->id;
					// $paiement->socid		= $facture->id;
					$paiement->note_private = "Automatic payment by ScanInvoices script on " . date("r");

					dol_syslog("ScanInvoices::Supplierautopayinvoices Paiement : " . json_encode($paiement), LOG_DEBUG);

					$paiement_id = $paiement->create($user, 1, $thirdparty);
					dol_syslog("ScanInvoices::Supplierautopayinvoices Paiement created ? : " . $paiement_id, LOG_DEBUG);
					if ($paiement_id < 0) {
						setEventMessages($paiement->error, $paiement->errors, 'errors');
						$error++;
						$localerror++;
					}

					if (!$localerror) {
						dol_syslog("ScanInvoices::Supplierautopayinvoices No error next", LOG_DEBUG);
						$label = '(SupplierInvoicePayment)';
						$result = $paiement->addPaymentToBank($user, 'payment_supplier', $label, $facture->fk_account, '', '');
						if ($result < 0) {
							// dol_syslog("No error result = $result", LOG_DEBUG);
							setEventMessages($paiement->error, $paiement->errors, 'errors');
							$error++;
							$localerror++;
						}
					}
					dol_syslog("ScanInvoices::Supplierautopayinvoices before commit / rollback", LOG_DEBUG);

					if ($localerror == 0) {
						$db->commit();
						$this->output .= "Payment added for invoice ref ".$facture->ref."\n";
					} else {
						$db->rollback();
						$this->output .= "erreur : " . $obj->error . " ::  " .  $obj->errors;
					}
					$i++;
				}
			} else {
				$this->output .= "No unpaid invoices found\n";
			}
		}
		return $error;
	}
}
