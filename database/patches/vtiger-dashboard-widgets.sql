DROP PROCEDURE IF EXISTS vtiger_dashboard_widgets_schema_patch;

DELIMITER //
CREATE PROCEDURE vtiger_dashboard_widgets_schema_patch()
BEGIN
	IF NOT EXISTS (
		SELECT 1
		FROM information_schema.tables
		WHERE table_schema = DATABASE()
			AND table_name = 'vtiger_module_dashboard_widgets'
	) THEN
		SIGNAL SQLSTATE '45000'
			SET MESSAGE_TEXT = 'Run this patch after completing the Vtiger installer';
	END IF;

	IF NOT EXISTS (
		SELECT 1
		FROM information_schema.tables
		WHERE table_schema = DATABASE()
			AND table_name = 'vtiger_dashboard_tabs'
	) THEN
		SIGNAL SQLSTATE '45000'
			SET MESSAGE_TEXT = 'Dashboard tabs table is missing; check the Vtiger installation';
	END IF;

	IF NOT EXISTS (
		SELECT 1
		FROM information_schema.columns
		WHERE table_schema = DATABASE()
			AND table_name = 'vtiger_module_dashboard_widgets'
			AND column_name = 'reportid'
	) THEN
		ALTER TABLE vtiger_module_dashboard_widgets
			ADD COLUMN reportid INT(19) DEFAULT NULL;
	END IF;

	IF NOT EXISTS (
		SELECT 1
		FROM information_schema.columns
		WHERE table_schema = DATABASE()
			AND table_name = 'vtiger_module_dashboard_widgets'
			AND column_name = 'dashboardtabid'
	) THEN
		ALTER TABLE vtiger_module_dashboard_widgets
			ADD COLUMN dashboardtabid INT(11) DEFAULT NULL;
	END IF;

	IF NOT EXISTS (
		SELECT 1
		FROM information_schema.columns
		WHERE table_schema = DATABASE()
			AND table_name = 'vtiger_module_dashboard_widgets'
			AND column_name = 'size'
	) THEN
		ALTER TABLE vtiger_module_dashboard_widgets
			ADD COLUMN size VARCHAR(50) DEFAULT NULL;
	END IF;

	UPDATE vtiger_module_dashboard_widgets AS widget
	INNER JOIN (
		SELECT userid, MIN(id) AS id
		FROM vtiger_dashboard_tabs
		WHERE isdefault = 1
		GROUP BY userid
	) AS dashboard_tab ON dashboard_tab.userid = widget.userid
	SET widget.dashboardtabid = dashboard_tab.id
	WHERE widget.dashboardtabid IS NULL;

	IF NOT EXISTS (
		SELECT 1
		FROM information_schema.key_column_usage
		WHERE table_schema = DATABASE()
			AND table_name = 'vtiger_module_dashboard_widgets'
			AND column_name = 'dashboardtabid'
			AND referenced_table_name = 'vtiger_dashboard_tabs'
	) THEN
		ALTER TABLE vtiger_module_dashboard_widgets
			ADD CONSTRAINT fk_vtiger_module_dashboard_widgets_tab
			FOREIGN KEY (dashboardtabid) REFERENCES vtiger_dashboard_tabs(id)
			ON DELETE CASCADE;
	END IF;
END//
DELIMITER ;

CALL vtiger_dashboard_widgets_schema_patch();
DROP PROCEDURE vtiger_dashboard_widgets_schema_patch;
