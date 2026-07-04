-- cancel update 05 due to old mysql < 5.6 where datetime could not be default set from timestamp
ALTER TABLE `llx_scaninvoices_filestoimport` CHANGE `date_ocr_send` `date_ocr_send` DATETIME NOT NULL DEFAULT '2000-01-01'; 
ALTER TABLE `llx_scaninvoices_filestoimport` CHANGE `date_ocr_return` `date_ocr_return` DATETIME NOT NULL DEFAULT '2000-01-01'; 
ALTER TABLE `llx_scaninvoices_filestoimport` MODIFY COLUMN `status` smallint DEFAULT '0';
