UPDATE `llx_scaninvoices_filestoimport` SET status='3' WHERE status='-1';
UPDATE `llx_scaninvoices_filestoimport` SET status='5' WHERE fk_supplier IS NOT NULL OR fk_invoice IS NOT NULL;
UPDATE `llx_scaninvoices_filestoimport` SET status='2' WHERE fk_supplier IS NOT NULL AND fk_invoice IS NOT NULL;
