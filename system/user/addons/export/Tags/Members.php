<?php

namespace Mithra62\Export\Tags;

/**
 * {exp:export:members} — exports member rows including custom member fields.
 *
 * Optional params:
 *   roles="1|3"              Filter by primary role ID (pipe-separated)
 *   join_start="2024-01-01"  Filter join date from (any PHP-parseable date)
 *   join_end="2024-12-31"    Filter join date to
 *   last_login_start="..."   Filter last visit from
 *   last_login_end="..."     Filter last visit to
 *   limit="500"              Maximum members to export
 *   offset="0"               Pagination offset
 *   chunk_size="500"         Members per streaming chunk (default: 500)
 *   search:field_name="val"  Filter by any member or custom field value
 *   fields="col1|col2"       Whitelist — return only these output columns
 *   exclude="col1|col2"      Blacklist — remove these output columns
 *
 * Example:
 *   {exp:export:members
 *       roles="5"
 *       join_start="2024-01-01"
 *       join_end="2024-12-31"
 *       exclude="password|salt"
 *       format="csv"
 *       output="download"
 *       output:filename="members-2024.csv"
 *   }
 */
class Members extends AbstractTag
{
    public function process()
    {
        $params = $this->params();

        $params['source'] = 'members';
        $params['source:roles'] = $this->explodeParam('roles');
        $params['source:limit'] = $this->param('limit');
        $params['source:offset'] = $this->param('offset', 0);
        $params['source:chunk_size'] = $this->param('chunk_size', 500);
        $params['source:join_start'] = $this->param('join_start');
        $params['source:join_end'] = $this->param('join_end');
        $params['source:last_login_start'] = $this->param('last_login_start');
        $params['source:last_login_end'] = $this->param('last_login_end');

        $this->compile($params);
    }
}
