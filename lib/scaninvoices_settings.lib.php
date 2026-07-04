<?php
/* Copyright (C) ---Put here your own copyright and developer email---
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
 * \file    lib/scaninvoices_settings.lib.php
 * \ingroup scaninvoices
 * \brief   Library files with common functions for Settings
 */

/**
 * Prepare array of tabs for Settings
 *
 * @param	Settings	$object		Settings
 * @return 	array					Array of tabs
 */
function settingsPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load("scaninvoices@scaninvoices");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/scaninvoices/settings_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Card");
	$head[$h][2] = 'card';
	$h++;

	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
	$upload_dir = $conf->scaninvoices->dir_output."/settings/".dol_sanitizeFileName($object->ref);
	$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', ['(\.meta|_preview.*\.png)$']));
	$nbLinks = Link::count($db, $object->element, $object->id);
	// $head[$h][0] = dol_buildpath("/scaninvoices/settings_document.php", 1).'?id='.$object->id;
	// $head[$h][1] = $langs->trans('Documents');
	// if (($nbFiles + $nbLinks) > 0) $head[$h][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
	// $head[$h][2] = 'document';
	// $h++;

	// $head[$h][0] = dol_buildpath("/scaninvoices/settings_agenda.php", 1).'?id='.$object->id;
	// $head[$h][1] = $langs->trans("Events");
	// $head[$h][2] = 'agenda';
	// $h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@scaninvoices:/scaninvoices/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@scaninvoices:/scaninvoices/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'settings@scaninvoices');

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'settings@scaninvoices', 'remove');

	return $head;
}
