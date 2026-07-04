<?php
/* Copyright (C) 2021 Éric Seigne <eric.seigne@cap-rel.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    scaninvoices/class/actions_scaninvoices.class.php
 * \ingroup scaninvoices
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

dol_include_once('/scaninvoices/class/filestoimport.class.php');
dol_include_once('/scaninvoices/lib/scaninvoices_filestoimport.lib.php');
dol_include_once('/scaninvoices/lib/scaninvoices.lib.php');

/**
 * Class ActionsScanInvoices
 */
class ActionsScanInvoices
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;


	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Execute action
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					<0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action)
	{
		global $db, $langs, $conf, $user;

		// dol_syslog("******************************* doActions parameters ::" . json_encode($parameters));
		// dol_syslog("******************************* doActions object " . json_encode($object));
		// dol_syslog("******************************* doActions action " . json_encode($action));

		$this->resprints = '';
		return 0;
	}


	/**
	 *    Execute action
	 *
	 *    @param	array	$parameters				Array of parameters
	 *    @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 *    @param    string	$action      			'add', 'update', 'view'
	 *    @return   int         					<0 if KO,
	 *                              				=0 if OK but we want to process standard actions too,
	 *                              				>0 if OK and we want to replace standard actions.
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action)
	{
		global $langs, $conf, $user;

		// dol_syslog(" ************************* " . get_class($this) . '::addMoreActionsButtons action=' . $action . " parameters=" . json_encode($object));
		$langs->load("scaninvoices@scaninvoices");
		// v17 currentcontext change
		if (in_array('ordersuppliercard', explode(':', $parameters['context'])) && $object->statut > 2) {
			$alt = $langs->trans("ScanInvoicesFromSupplierOrder");
			print '<a class="butAction" href="' . dol_buildpath('/scaninvoices/importinvoice.php', 2) . '?step=2&origin=order_supplier&fournID=' . $object->fourn_id . '&orderID=' . $object->id . '&token=' . urlencode(newToken()) . '" title="' . dol_escape_htmltag($alt) . '">' . $langs->trans('ScanInvoice') . '</a>';
		}

		// dol_syslog("******************************* addMoreActionsButtons parameters ::" . json_encode($parameters));
		// dol_syslog("******************************* addMoreActionsButtons object " . json_encode($object));
		return;
		// if (in_array($parameters['currentcontext'], array('contractcard'))				// do something only for the context 'contractcard'
		//     if ($user->rights->sellyoursaas->write) {
		//         if (in_array($object->array_options['options_deployment_status'], array('processing', 'undeployed'))) {
		//             $alt = $langs->trans("SellYourSaasSubDomains").' '.getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_NAMES');
		//             $alt.= '<br>'.$langs->trans("SellYourSaasSubDomainsIP").' '.getDolGlobalString('SELLYOURSAAS_SUB_DOMAIN_IP');

		//             print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deploy&token='.urlencode(newToken()).'" title="'.dol_escape_htmltag($alt).'">' . $langs->trans('Redeploy') . '</a>';
		//         } else {
		//             print '<a class="butActionRefused" href="#" title="'.$langs->trans("ContractMustHaveStatusProcessingOrUndeployed").'">' . $langs->trans('Redeploy') . '</a>';
		//         }

		//         if (in_array($object->array_options['options_deployment_status'], array('done'))) {
		//             print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=refresh&token='.urlencode(newToken()).'">' . $langs->trans('RefreshRemoteData') . '</a>';

		//             if (empty($object->array_options['options_fileauthorizekey'])) {
		//                 print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=recreateauthorizedkeys&token='.urlencode(newToken()).'">' . $langs->trans('RecreateAuthorizedKey') . '</a>';
		//             }

		//             /*if (empty($object->array_options['options_filelock']))
		//             {
		//                 print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=recreatelock&token='.newToken().'">' . $langs->trans('RecreateLock') . '</a>';
		//             }
		//             else
		//             {
		//                 print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=deletelock&token='.newToken().'">' . $langs->trans('SellYourSaasRemoveLock') . '</a>';
		//             }*/
		//         } else {
		//             print '<a class="butActionRefused" href="#" title="'.$langs->trans("ContractMustHaveStatusDone").'">' . $langs->trans('RefreshRemoteData') . '</a>';
		//         }

		//         if (in_array($object->array_options['options_deployment_status'], array('done'))) {
		//             if (empty($object->array_options['options_suspendmaintenance_message'])) {
		//                 print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=suspendmaintenancetoconfirm&token='.urlencode(newToken()).'">' . $langs->trans('Maintenance') . '</a>';
		//             } else {
		//                 print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=unsuspend&token='.urlencode(newToken()).'">' . $langs->trans('StopMaintenance') . '</a>';
		//             }
		//         }

		//         if (in_array($object->array_options['options_deployment_status'], array('done'))) {
		//             print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=undeploy&token='.urlencode(newToken()).'">' . $langs->trans('Undeploy') . '</a>';
		//         } else {
		//             print '<a class="butActionRefused" href="#" title="'.$langs->trans("ContractMustHaveStatusDone").'">' . $langs->trans('Undeploy') . '</a>';
		//         }

		//         print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=changecustomer&token='.urlencode(newToken()).'" title="'.$langs->trans("ChangeCustomer").'">' . $langs->trans('ChangeCustomer') . '</a>';
		//     }
		// }

		// return 0;
	}


	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		// dol_syslog("******************************* doActions parameters ::" . json_encode($parameters));
		// dol_syslog("******************************* doActions object " . json_encode($object));
		// dol_syslog("******************************* doActions action " . json_encode($action));
		// dol_syslog("******************************* doActions hookmanager " . json_encode($hookmanager));

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {	    // do something only for the context 'somecontext1' or 'somecontext2'
			// Do what you want here...
			// You can for example call global vars like $fieldstosearchall to overwrite them, or update database depending on $action and $_POST values.
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}


	/**
	 * Overloading the doMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
			foreach ($parameters['toselect'] as $objectid) {
				// Do action on each object id
			}
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}


	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$disabled = 1;

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
			$this->resprints = '<option value="0"' . ($disabled ? ' disabled="disabled"' : '') . '>' . $langs->trans("ScanInvoicesMassAction") . '</option>';
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}



	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$object		   	Object output on PDF
	 * @param   string	$action     	'add', 'update', 'view'
	 * @return  int 		        	<0 if KO,
	 *                          		=0 if OK but we want to process standard actions too,
	 *  	                            >0 if OK and we want to replace standard actions.
	 */
	public function beforePDFCreation($parameters, &$object, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0;
		$deltemp = array();
		dol_syslog(get_class($this) . '::executeHooks action=' . $action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}

	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$pdfhandler     PDF builder handler
	 * @param   string	$action         'add', 'update', 'view'
	 * @return  int 		            <0 if KO,
	 *                                  =0 if OK but we want to process standard actions too,
	 *                                  >0 if OK and we want to replace standard actions.
	 */
	public function afterPDFCreation($parameters, &$pdfhandler, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0;
		$deltemp = array();
		dol_syslog(get_class($this) . '::executeHooks action=' . $action);

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {
			// do something only for the context 'somecontext1' or 'somecontext2'
		}

		return $ret;
	}



	/**
	 * Overloading the loadDataForCustomReports function : returns data to complete the customreport tool
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function loadDataForCustomReports($parameters, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$langs->load("scaninvoices@scaninvoices");

		$this->results = array();

		$head = array();
		$h = 0;

		if ($parameters['tabfamily'] == 'scaninvoices') {
			$head[$h][0] = dol_buildpath('/module/index.php', 1);
			$head[$h][1] = $langs->trans("Home");
			$head[$h][2] = 'home';
			$h++;

			$this->results['title'] = $langs->trans("ScanInvoices");
			$this->results['picto'] = 'scaninvoices@scaninvoices';
		}

		$head[$h][0] = 'customreports.php?objecttype=' . $parameters['objecttype'] . (empty($parameters['tabfamily']) ? '' : '&tabfamily=' . $parameters['tabfamily']);
		$head[$h][1] = $langs->trans("CustomReports");
		$head[$h][2] = 'customreports';

		$this->results['head'] = $head;

		return 1;
	}



	/**
	 * Overloading the restrictedArea function : check permission on an object
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int 		      			  	<0 if KO,
	 *                          				=0 if OK but we want to process standard actions too,
	 *  	                            		>0 if OK and we want to replace standard actions.
	 */
	public function restrictedArea($parameters, &$action, $hookmanager)
	{
		global $user;

		if ($parameters['features'] == 'myobject') {
			if ($user->rights->scaninvoices->read) {
				$this->results['result'] = 1;
				return 1;
			} else {
				$this->results['result'] = 0;
				return 1;
			}
		}

		return 0;
	}

	/**
	 * hook to get files attachment on email via dolibarr mail collector core module
	 *
	 * @param   [type]  $parameters  [$parameters description]
	 * @param   [type]  $data        [$data description]
	 * @param   [type]  $operation   [$operation description]
	 *
	 * @return  [type]               [return description]
	 */
	public function addmoduletoeamailcollectorjoinpiece($parameters, &$data, &$operation)
	{
		global $user;
		$documentAjoute = 0;

		include_once DOL_DOCUMENT_ROOT.'/emailcollector/lib/emailcollector.lib.php';
		if ((float) DOL_VERSION < 15.0) {
			dol_syslog("addmoduletoeamailcollectorjoinpiece function saveAttachment exists on dolibarr 15.0+; please upgrade", LOG_ERR);
			return 0;
		}


		// dol_syslog("******************************* doActions parameters ::" . json_encode($parameters));
		// dol_syslog("******************************* doActions data " . json_encode($data));
		// dol_syslog("******************************* doActions operation " . json_encode($operation));

		//if not for scaninvoices -> exit
		if (!isset($operation['actionparam']) || $operation['actionparam'] !== "scaninvoices") {
			return 0;
		}

		$acceptFiles = ['pdf','jpg','jpeg'];
		$dirupload = DOL_DATA_ROOT.'/scaninvoices/uploads/later/';
		$html = "<h3>Compte rendu d'import des pièces jointes de mail</h3>";

		foreach ($data as $filenameTmp => $content) {
			$extension = pathinfo($filenameTmp, PATHINFO_EXTENSION);
			$filename = "";
			if ($extension == "pdf") {
				$filename = dol_sanitizeFileName(scaninvoicesSlugify(basename($filenameTmp, $extension))).'.pdf';
			} elseif ($extension == "jpg" || $extension == "jpeg") {
				$filename = dol_sanitizeFileName(scaninvoicesSlugify(basename($filenameTmp, $extension))).'.jpg';
			} else {
				dol_syslog("ScanInvoices email import : take only pdf and jpg/jpeg files, not $extension");
				return 0;
			}

			dol_syslog("addmoduletoeamailcollectorjoinpiece dirupload=$dirupload filename=$filename");
			$resr = saveAttachment($dirupload, $filename, $content);
			if ($resr == -1) {
				dol_syslog("addmoduletoeamailcollectorjoinpiece: doc not saved");
				$this->errors[] = 'Doc not saved';
			} else {
				dol_syslog("addmoduletoeamailcollectorjoinpiece: doc saved to $resr");
				$object = new Filestoimport($this->db);

				$sha1 = sha1_file($resr);
				$resultAll = $object->fetchAll('', '', 0, 0, array('customsql'=>"t.sha1='" . $sha1 . "'"));
				if ($resultAll) {
					$object = reset($resultAll);
					@unlink($resr);
					dol_syslog("addmoduletoeamailcollectorjoinpiece: duplicate for $resr");
					continue;
				}

				$this->db->begin();
				$object->filename = $filename;
				$object->date_creation = dol_now();
				$object->import_key = 'ml'.dol_now();
				$object->status = Filestoimport::STATUS_DRAFT;
				$object->queue = Filestoimport::QUEUE_LATER;
				$object->sha1 = $sha1;
				$objectid = $object->create($user);
				if ($objectid <= 0) {
					$this->db->rollback();
					@unlink($dirupload . $filename);
					dol_syslog("ScanInvoices : " . $filename . " error database access : " . $object->error);
				} else {
					$documentAjoute++;
					$html .= "<p>Le document $filename a été ajouté dans la file de traitement de ScanInvoices.</p>";
					dol_syslog("ScanInvoices : " . $filename . " pushed to import queue success !");
					$this->db->commit();
				}
			}
		}
		$html .= "</p>";
		if ($documentAjoute > 0) {
			scaninvoicesSendMail(getDolGlobalString('SCANINVOICES_IMPORT_SHARE_MAILREPORT'), $langs->trans("MailRepporting"), '', '', $html);
		}

		return 1;
	}

	/* Add here any other hooked methods... */
}
