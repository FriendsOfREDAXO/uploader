<?php

namespace FriendsOfRedaxo\Uploader;

/**
 * Class BulkReworkList
 *
 * @category
 * @package uploader\lib
 * @author Peter Schulze | p.schulze[at]bitshifters.de
 * @created 05.06.2025
 */
class BulkReworkList extends \rex_list
{
    /**
     * getting current sql query
     *
     * @return \rex_sql
     * @author Peter Schulze | p.schulze[at]bitshifters.de
     * @created 05.06.2025
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * setting custom sql query
     *
     * @param $query
     * @return void
     * @throws \rex_sql_exception
     * @author Peter Schulze | p.schulze[at]bitshifters.de
     * @created 05.06.2025
     */
    public function setCustomQuery($query)
    {
        $this->sql->setQuery($query);
    }
}