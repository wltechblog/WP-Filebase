	static function SyncCustomFields($remove = false)
	{
		global $wpdb;
		$synced = true;

		$messages = array();

		// Use safer query with proper table reference
		$table_name = $wpdb->wpfilebase_files;
		$cols = $wpdb->get_col("SHOW COLUMNS FROM $table_name LIKE 'file_custom_%'");

		$custom_fields = WPFB_Core::GetCustomFields();
		foreach ($custom_fields as $ct => $cn) {
			if (!in_array('file_custom_' . $ct, $cols)) {
				// Use esc_sql for column name as it can't be parameterized
				$column_name = 'file_custom_' . esc_sql($ct);
				$query = "ALTER TABLE " . $table_name . " ADD `" . $column_name . "` TEXT NOT NULL";
				$messages[] = sprintf($wpdb->query($query) ?
								 __('Custom field \'%s\' added.', 'wp-filebase') : __('Could not add custom field \'%s\'!', 'wp-filebase'), $cn);
			}
		}

		if (!$remove) {
			foreach ($cols as $cf) {
				$ct = substr($cf, 12); // len(file_custom_)
				if (!isset($custom_fields[$ct])) {
					// Use esc_sql for column name as it can't be parameterized
					$column_name = esc_sql($cf);
					$query = "ALTER TABLE " . $table_name . " DROP `" . $column_name . "`";
					$messages[] = sprintf($wpdb->query($query) ?
									 __('Custom field \'%s\' removed!', 'wp-filebase') : __('Could not remove custom field \'%s\'!', 'wp-filebase'), $ct);
				}
			}
		}

		return $messages;
	}