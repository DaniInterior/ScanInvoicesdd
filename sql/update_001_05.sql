-- ALTER TABLE `llx_scaninvoices_filestoimport` CHANGE `date_ocr_send` `date_ocr_send` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ; 
-- ALTER TABLE `llx_scaninvoices_filestoimport` CHANGE `date_ocr_return` `date_ocr_return` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ; 
ALTER TABLE `llx_scaninvoices_filestoimport` CHANGE `tms` `tms` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE llx_scaninvoices_filestoimport ADD COLUMN entity integer DEFAULT 1;

