<?php

namespace Mithra62\Export\Tags;

class Query extends AbstractTag
{
    /**
     * @return void
     */
    public function process()
    {
        $params = $this->params();
        $params['source']       = 'sql';
        $params['source:query'] = $this->param('sql');

        // SQL defaults to Super Admin only when no explicit allowed_roles is set.
        // Set allowed_roles in the CP config or tag params to broaden access.
        if (empty($params['allowed_roles']) && ! ee('Permission')->isSuperAdmin()) {
            show_error(lang('export_err_sql_superadmin_only'), 403);
            return;
        }

        $this->compile($params);
    }
}
