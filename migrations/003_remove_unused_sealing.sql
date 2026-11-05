-- Sealing.php uses the restored sheet integration, not the abandoned local API.
-- Keep populated tables, attachment references and foreign-key dependencies intact.
SET @qachem_sealing_exists = (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='t_qc_sealing' AND table_type='BASE TABLE');
SET @qachem_sealing_rows = 0;
SET @qachem_sealing_sql = IF(@qachem_sealing_exists>0, 'SELECT COUNT(*) INTO @qachem_sealing_rows FROM t_qc_sealing', 'DO 0');
PREPARE qachem_sealing_cleanup FROM @qachem_sealing_sql;
EXECUTE qachem_sealing_cleanup;
DEALLOCATE PREPARE qachem_sealing_cleanup;
SET @qachem_sealing_references = (SELECT COUNT(*) FROM information_schema.key_column_usage WHERE referenced_table_schema=DATABASE() AND referenced_table_name='t_qc_sealing');
SET @qachem_sealing_attachments = (SELECT COUNT(*) FROM t_qc_attachment WHERE entity_type='SEALING');
SET @qachem_sealing_sql = IF(@qachem_sealing_exists>0 AND @qachem_sealing_rows=0 AND @qachem_sealing_references=0 AND @qachem_sealing_attachments=0, 'DROP TABLE t_qc_sealing', 'DO 0');
PREPARE qachem_sealing_cleanup FROM @qachem_sealing_sql;
EXECUTE qachem_sealing_cleanup;
DEALLOCATE PREPARE qachem_sealing_cleanup;
