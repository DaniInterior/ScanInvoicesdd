-- Copyright (C) ---Put here your own copyright and developer email---
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.


CREATE TABLE llx_scaninvoices_filestoimport(
	-- BEGIN MODULEBUILDER FIELDS
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity integer DEFAULT 1,
	ref varchar(128) DEFAULT '(PROV)' NOT NULL, 
	filename varchar(255), 
	sha1 varchar(40), 
	message text, 
	date_creation datetime NOT NULL DEFAULT '2000-01-01', 
	date_ocr_send datetime NOT NULL DEFAULT '2000-01-01', 
	date_ocr_return datetime NOT NULL DEFAULT '2000-01-01', 
	tms TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
	fk_supplier integer, 
	fk_invoice integer, 
	fk_user_creat integer NOT NULL, 
	fk_user_modif integer, 
	import_key varchar(14), 
	queue smallint, 
	status smallint DEFAULT '0'
	-- END MODULEBUILDER FIELDS
) ENGINE=innodb;
