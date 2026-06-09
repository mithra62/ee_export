<?php

$lang = [
    // Module identity
    'export_module_name'        => 'Export',
    'export_module_description' => 'Export channel entries, members, grid rows, fluid instances, or query results to CSV, JSON, XLSX, or XML.',
    'export_settings'           => 'Export Settings',

    // ── CP general ────────────────────────────────────────────────────────────
    'export_cp_heading'      => 'Export',
    'export_create_heading'  => 'Create Export',
    'export_edit_heading'    => 'Edit Export',
    'export_delete_heading'  => 'Delete Export',
    'export_run_heading'     => 'Run Export',

    // Actions / buttons
    'export_create_new'      => 'Create Export',
    'export_save'            => 'Save Export',
    'export_saving'          => 'Saving…',
    'export_run'             => 'Run',
    'export_edit'            => 'Edit',
    'export_delete'          => 'Delete',
    'export_remove'          => 'Remove',

    // Index table columns
    'export_col_name'        => 'Name',
    'export_col_source'      => 'Source',
    'export_col_format'      => 'Format',
    'export_col_output'      => 'Output',
    'export_col_created'     => 'Created',

    // Empty state
    'export_no_configs'      => 'No exports saved yet.',
    'export_create_first'    => 'Create your first export configuration to get started.',

    // Alerts
    'export_saved_success'   => 'Export configuration saved.',
    'export_deleted_success' => 'Export configuration deleted.',
    'export_deleted_body'    => '"%s" has been removed.',
    'export_run_success'     => 'Export completed successfully.',
    'export_run_failed'      => 'Export failed.',
    'export_delete_confirm'  => 'Are you sure you want to delete this export configuration?',

    // ── Form — field labels ───────────────────────────────────────────────────
    'export_field_name'                 => 'Export Name',
    'export_field_name_desc'            => 'A unique, human-readable label for this saved configuration.',
    'export_field_source'               => 'Source',
    'export_field_source_desc'          => 'The data source to export.',
    'export_field_channel'              => 'Channel',
    'export_field_field'                => 'Field',
    'export_field_status'               => 'Status',
    'export_field_author_id'            => 'Author ID',
    'export_field_entry_id'             => 'Entry ID(s)',
    'export_field_limit'                => 'Limit',
    'export_field_offset'               => 'Offset',
    'export_field_chunk_size'           => 'Chunk Size',
    'export_field_relationship_fields'  => 'Relationship Fields',
    'export_field_roles'                => 'Member Roles',
    'export_field_join_start'           => 'Joined After',
    'export_field_join_end'             => 'Joined Before',
    'export_field_last_login_start'     => 'Last Login After',
    'export_field_last_login_end'       => 'Last Login Before',
    'export_field_sql'                  => 'SQL Query',
    'export_field_filename'             => 'Filename',
    'export_field_path'                 => 'Save Path',
    'export_field_path_desc'            => 'Absolute server path to the directory where the file should be saved (local output only).',

    // ── Form — section headings ───────────────────────────────────────────────
    'export_section_source_params'      => 'Source Options',
    'export_section_source_params_desc' => 'Configure filtering and pagination for the selected source.',
    'export_section_columns'            => 'Column Selection',
    'export_section_columns_desc'       => 'Optionally whitelist or blacklist output columns. Leave at "All columns" to export everything.',
    'export_section_format'             => 'Format',
    'export_section_format_options'     => 'Format Options',
    'export_section_format_options_desc'=> 'Additional options specific to the selected format.',
    'export_section_output'             => 'Output / Destination',
    'export_section_modifiers'          => 'Modifiers',
    'export_section_modifiers_desc'     => 'Post-process individual column values. Chain modifiers with |. Example: ee_date[%Y-%m-%d]|uc_first',

    // ── Form — source choices ─────────────────────────────────────────────────
    'export_source_entries' => 'Channel Entries',
    'export_source_members' => 'Members',
    'export_source_grid'    => 'Grid Field',
    'export_source_fluid'   => 'Fluid Field',
    'export_source_sql'     => 'SQL Query',

    // ── Form — status choices ─────────────────────────────────────────────────
    'export_status_open'    => 'Open',
    'export_status_closed'  => 'Closed',
    'export_status_all'     => 'All',

    // ── Form — output choices ─────────────────────────────────────────────────
    'export_output_download' => 'Browser Download',
    'export_output_local'    => 'Save to Server',

    // ── Form — column selection ───────────────────────────────────────────────
    'export_col_all'              => 'All columns',
    'export_col_whitelist'        => 'Include only',
    'export_col_blacklist'        => 'Exclude',
    'export_no_columns_available' => 'Select a channel (and field for Grid/Fluid) to load available columns.',

    // ── Form — format option labels & descriptions ────────────────────────────
    'export_format_separator'         => 'Column Separator',
    'export_format_separator_desc'    => 'Single character used between column values.',
    'export_format_enclosure'         => 'Enclosure Character',
    'export_format_enclosure_desc'    => 'Single character used to wrap field values that contain the separator.',
    'export_format_escape'            => 'Escape Character',
    'export_format_escape_desc'       => 'Single character used to escape the enclosure inside a value.',
    'export_format_newline'           => 'Line Ending',
    'export_format_newline_desc'      => 'Line-ending sequence written between rows.',
    'export_format_bold_cols'         => 'Bold Header Row',
    'export_format_bold_cols_desc'    => 'Apply bold formatting to the first (header) row.',
    'export_format_sheet_name'        => 'Sheet Name',
    'export_format_sheet_name_desc'   => 'Name for the worksheet tab. Defaults to Sheet1.',
    'export_format_root_name'         => 'Root Element Name',
    'export_format_root_name_desc'    => 'XML tag that wraps all rows. Must start with a letter.',
    'export_format_branch_name'       => 'Row Element Name',
    'export_format_branch_name_desc'  => 'XML tag for each individual row. Must start with a letter.',

    // ── Form — misc ───────────────────────────────────────────────────────────
    'export_select_none'             => '— Select —',
    'export_select_channel_first'   => '— Select a channel first —',
    'export_hint_pipe_sep'           => 'Pipe-separated. Example: value1|value2',
    'export_placeholder_no_limit'    => 'No limit',
    'export_placeholder_any'         => 'Any',
    'export_modifier_column'      => 'Column Name',
    'export_modifier_chain'       => 'Modifier Chain',
    'export_add_modifier'         => 'Add Modifier',

    // ── Errors ────────────────────────────────────────────────────────────────
    'export_err_heading'    => 'Your submission has errors',
    'export_err_fix_below'  => 'Please correct the fields highlighted below.',
    'export_err_not_found'  => 'Export configuration not found.',


    '' => '',
];
