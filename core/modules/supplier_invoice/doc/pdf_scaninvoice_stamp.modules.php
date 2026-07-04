<?php
/* Copyright (C) 2010-2011  Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2010-2014  Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2015       Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2018-2021  Frédéric France      <frederic.france@netlogic.fr>
 * Copyright (C) 2022  		Éric <Seigne>		 <eric.seigne@cap-rel.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/supplier_invoice/doc/pdf_scaninvoice_stamp.modules.php
 *	\ingroup    fournisseur
 *	\brief      Class file to generate the supplier invoices with the scaninvoice_stamp model
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/supplier_invoice/modules_facturefournisseur.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';


/**
 *	Class to generate the supplier invoices PDF with the template scaninvoice_stamp
 */
class pdf_scaninvoice_stamp extends ModelePDFSuppliersInvoices
{
	public $result;

	/**
	 * @var DoliDb Database handler
	 */
	public $db;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description (short text)
	 */
	public $description;

	/**
	 * @var int 	Save the name of generated file as the main doc when generating a doc with this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * @var array Minimum version of PHP required by module.
	 * e.g.: PHP ≥ 5.6 = array(5, 6)
	 */
	public $phpmin = array(5, 6);

	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * @var int page_largeur
	 */
	public $page_largeur;

	/**
	 * @var int page_hauteur
	 */
	public $page_hauteur;

	/**
	 * @var array format
	 */
	public $format;

	/**
	 * @var int marge_gauche
	 */
	public $marge_gauche;

	/**
	 * @var int marge_droite
	 */
	public $marge_droite;

	/**
	 * @var int marge_haute
	 */
	public $marge_haute;

	/**
	 * @var int marge_basse
	 */
	public $marge_basse;

	/**
	 * Issuer
	 * @var Societe object that emits
	 */
	public $emetteur;



	/**
	 *	Constructor
	 *
	 *  @param	DoliDB		$db     	Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		// Translations
		$langs->loadLangs(array("main", "bills", "scaninvoices"));

		$this->db = $db;
		$this->name = "scaninvoice_stamp";
		$this->description = $langs->trans('SuppliersScanInvoiceModel');
		$this->update_main_doc_field = 1; // Save the name of generated file as the main doc when generating a doc with this template

		// Page dimensions
		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT') ? getDolGlobalInt('MAIN_PDF_MARGIN_LEFT') : 10;
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT') ? getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT') : 10;
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP') ? getDolGlobalInt('MAIN_PDF_MARGIN_TOP') : 10;
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM') ? getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM') : 10;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build pdf onto disk
	 *
	 *  @param		FactureFournisseur	$object				Object to generate
	 *  @param		Translate			$outputlangs		Lang output object
	 *  @param		string				$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int					$hidedetails		Do not show line details
	 *  @param		int					$hidedesc			Do not show desc
	 *  @param		int					$hideref			Do not show ref
	 *  @return		int										1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs = '', $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $langs, $conf, $mysoc, $hookmanager, $nblines;

		// Get source company
		if (!is_object($object->thirdparty)) {
			$object->fetch_thirdparty();
		}
		if (!is_object($object->thirdparty)) {
			$object->thirdparty = $mysoc; // If fetch_thirdparty fails, object has no socid (specimen)
		}

		$this->emetteur = $object->thirdparty;
		if (!$this->emetteur->country_code) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2); // By default, if was not defined
		}

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (!empty(getDolGlobalString('MAIN_USE_FPDF'))) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		// Load translation files required by the page
		$outputlangs->loadLangs(array("main", "dict", "companies", "bills", "products"));

		$nblines = count($object->lines);

		if ($conf->fournisseur->facture->dir_output) {
			// Definition of $dir and $file
			$objectref = "";
			if ($object->specimen) {
				$dir = $conf->fournisseur->facture->dir_output;
				$file = $dir . "/SPECIMEN.pdf";
			} else {
				$objectref = dol_sanitizeFileName($object->ref);
				$objectrefsupplier = dol_sanitizeFileName($object->ref_supplier);
				$dir = $conf->fournisseur->facture->dir_output . '/' . get_exdir($object->id, 2, 0, 0, $object, 'invoice_supplier') . $objectref;
				$file = $dir . "/" . $objectref . ".pdf";
				if (!empty(getDolGlobalString('SUPPLIER_REF_IN_NAME'))) {
					$file = $dir . "/" . $objectref . ($objectrefsupplier ? "_" . $objectrefsupplier : "") . ".pdf";
				}
			}

			if (!file_exists($dir)) {
				if (dol_mkdir($dir) < 0) {
					$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
					return 0;
				}
			}

			if (file_exists($dir)) {
				// Create pdf instance
				/** @phpstan-ignore-next-line */
				$pdf = pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
				$pdf->SetAutoPageBreak(1, 0);

				if (class_exists('TCPDF')) {
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));

				//search for a pdf file $object->ref-xxxx.pdf in same folder
				$otherpdfindir = $othertmp = null;
				foreach (glob($dir . "/" . $objectref . "-*.pdf") as $otherpdf) {
					$otherpdfindir = $otherpdf;
				}

				if ((null !== $otherpdfindir) && (false === strpos($otherpdfindir, '('))) {
					dol_syslog("scaninvoice_stamp : break pdf security with qpdf");
					$othertmp = str_replace(".pdf", "-tmp.pdf", $otherpdfindir);
					if (is_file("/usr/bin/qpdf")) {
						$cmd = "/usr/bin/qpdf --decrypt " . escapeshellarg($otherpdfindir) . " "  . escapeshellarg($othertmp);
						if (false !== exec($cmd, $output)) {
							dol_syslog("scaninvoice_stamp : break pdf with qpdf ok $othertmp");
						} else {
							dol_syslog("scaninvoice_stamp : break pdf with qpdf error, try to continue, error is " . json_encode($output));
							copy($otherpdfindir, $othertmp);
						}
					} else {
						dol_syslog("scaninvoice_stamp : qpdf does not exists, probably that some pdf could not be processed", LOG_WARNING);
						copy($otherpdfindir, $othertmp);
					}

					dol_syslog("scaninvoice_stamp : try to setsourcefile with $othertmp");
					if (is_file($othertmp) && filesize($othertmp) > 50) {
						try {
							if ($pagecount = $pdf->setSourceFile($othertmp)) {
								dol_syslog("scaninvoice_stamp : set ok for $othertmp, pagecount=$pagecount, import page...");
								$tplidx = $pdf->importPage(1);
							} else {
								dol_syslog("scaninvoice_stamp : error using $othertmp as sourcefile ...");
							}
						} catch (Exception $e) {
							dol_syslog("scaninvoice_stamp : exception is " . $e->getMessage(), LOG_WARNING);
						}
					}
				}

				dol_syslog("scaninvoice_stamp : open pdf, page 0");
				$pdf->Open();
				$pagenb = 0;
				$pdf->SetDrawColor(128, 128, 128);

				dol_syslog("scaninvoice_stamp : set meta data to pdf");
				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("PdfInvoiceTitle"));
				$pdf->SetCreator("Dolibarr " . DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref) . " " . $outputlangs->transnoentities("PdfInvoiceTitle") . " " . $outputlangs->convToOutputCharset($object->thirdparty->name));
				if (!empty(getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION'))) {
					$pdf->SetCompression(false);
				}

				// $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

				// New page
				dol_syslog("scaninvoice_stamp : add page");
				$pdf->AddPage();
				if (!empty($tplidx)) {
					dol_syslog("scaninvoice_stamp : use template");
					$pdf->useTemplate($tplidx, null, null, $this->page_largeur, $this->page_hauteur, true);
				}
				$pagenb++;
				$this->_pagehead($pdf, $object, 1, $outputlangs);

				dol_syslog("scaninvoice_stamp : write pdf to $file");
				try {
					$pdf->Output($file, 'F');
				} catch (Exception $e) {
					dol_syslog("scaninvoice_stamp : exception on pdf close is " . $e->getMessage(), LOG_WARNING);
				}

				if (!empty(getDolGlobalString('MAIN_UMASK'))) {
					@chmod($file, octdec(getDolGlobalString('MAIN_UMASK')));
				}

				//clean up tmp file
				if (is_file($othertmp)) {
					@unlink($othertmp);
				}

				$this->result = array('fullpath' => $file);

				dol_syslog("scaninvoice_stamp : return 1");
				return 1; // No error
			} else {
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		} else {
			$this->error = $langs->transnoentities("ErrorConstantNotDefined", "SUPPLIER_OUTPUTDIR");
			return 0;
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page.
	 *
	 *  @param  TCPDF               $pdf            Object PDF
	 *  @param  FactureFournisseur  $object         Object to show
	 *  @param  int                 $showaddress    0=no, 1=yes
	 *  @param  Translate           $outputlangs    Object lang for output
	 *  @return int
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
	{
		global $langs, $conf, $mysoc;

		// Load translation files required by the page
		$outputlangs->loadLangs(array("main", "orders", "companies", "bills"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Do not add the BACKGROUND as this is for suppliers
		//pdf_pagehead($pdf,$outputlangs,$this->page_hauteur);

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$cellwidth = 35;
		$cellheight = 3;
		//erics
		$posy = 5;
		$posx = $this->page_largeur - $this->marge_droite - $cellwidth;

		$pdf->SetXY($this->marge_gauche, $posy);

		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->SetLineWidth(0.5);
		$pdf->SetDrawColor(200, 10, 10);
		$pdf->SetFillColor(255, 255, 255);
		$pdf->RoundedRect($posx, $posy, $cellwidth, $cellheight * 2, 1.5, '1111', 'FD');
		$pdf->SetTextColor(200, 10, 10);
		$pdf->MultiCell($cellwidth, $cellheight, $outputlangs->convToOutputCharset($object->ref), '', 'C');
		$posy += 1;

		return 0;
	}
}
