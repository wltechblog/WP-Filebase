<?php

class WPFB_Search
{

    /*
     *
     * TODO: search needs to be optimized.
     * - use keywords in id3 table only
     * - dont use LIKE, but MATCh function
     * - for wpfb_filepage, don't left join files at all! we should find the keywords in the post_content
     */

    static function sqlHooks()
    {
        if (WPFB_Core::$settings->search_integration) {
            add_filter('posts_join', array(__CLASS__, 'PostsJoin'));
            add_filter('posts_search', array(__CLASS__, 'PostsSearch'));
            add_filter('posts_groupby', array(__CLASS__, 'PostsGroupBy')); // for attachments
        }
    }

    static function sqlHooksPermsOnly()
    {
        add_filter('posts_join', array(__CLASS__, 'filepageJoin'));
        add_filter('posts_search', array(__CLASS__, 'addFilepagePermSql'));
    }

    static function PostsJoin($join)
    {
        global $wpdb;
        // TODO: this slows down the site
        // - might have been fixed, added index on file_post_id and file_wpattach_id
        // need performance test for cond '$wpdb->posts.post_type = 'wpfb_filepage' AND '
        // since post_type is not indexed
        $join .= " LEFT JOIN $wpdb->wpfilebase_files ON ( 
        $wpdb->wpfilebase_files.file_post_id = $wpdb->posts.ID
        OR ($wpdb->wpfilebase_files.file_wpattach_id = $wpdb->posts.ID)
        ) ";
        return $join;
    }


    static function filepageJoin($join)
    {
        global $wpdb;
        $join .= " LEFT JOIN $wpdb->wpfilebase_files
                ON ($wpdb->wpfilebase_files.file_wpattach_id = $wpdb->posts.ID) ";
        return $join;
    }


    private static function getSearchTerms($s)
    {
        // code extract from WPs search in query.php
        global $wp_query, $wpdb;

        $sentence = empty($wp_query->query_vars['sentence']) ? (empty($_GET['sentence']) ? null : sanitize_text_field(stripslashes($_GET['sentence']))) : $wp_query->query_vars['sentence'];
        $search_terms = array();

        if (!empty($s)) {
            $s = esc_sql(stripslashes($s));
            if ($sentence)
                $search_terms = array($s);
            else {
                preg_match_all('/".*?("|$)|((?<=[\\s",+])|^)[^\\s",+]+/', $s, $matches);
                $search_terms = array_map(function($a) {
                    return trim($a, "\"'\n\r ");
                }, $matches[0]);
            }
        }
        return $search_terms;
    }

// creates sql for searching files
    static function SearchWhereSql($search_id3 = false, $s = null)
    {
        global $wp_query, $wpdb;

        static $search_fields;

        if (empty($search_fields)) {
            $search_fields = array_merge(array(
                'file_name', 'file_thumbnail', 'file_display_name', 'file_description', 'file_tags',
                'file_requirement', 'file_version', 'file_author', 'file_language',
                'file_platform', 'file_license'), array_keys(WPFB_Core::GetCustomFields(true)));
        }

        if (empty($s)) {
            $s = empty($wp_query->query_vars['s']) ? (empty($_GET['s']) ? null : sanitize_text_field(stripslashes($_GET['s']))) : $wp_query->query_vars['s'];
            if (empty($s)) return null;
        }
        $exact = !empty($wp_query->query_vars['exact']);
        $p = $exact ? '' : '%';
        $search_terms = self::getSearchTerms($s);
        $where = "(1";

        // TODO: search fields with match...
        foreach ($search_terms as $term) {
            $where .= ($not = ($term[0] === '-')) ? " AND NOT (" : " AND (";
            if ($not) $term = substr($term, 1);

            $wc = strpos($term, '*') !== false; // check for wildcard

            $or = '';
            foreach ($search_fields as $sf) {
                $col = "{$wpdb->wpfilebase_files}.{$sf}";
                $where .= " {$or}({$col} LIKE '" . ($wc ? str_replace('*', '%', $term) : "{$p}{$term}{$p}") . "')";
                if (empty($or)) $or = 'OR ';
            }
            // ID3 search removed for PHP 8.0+ compatibility
            $where .= ") ";
        }
        $where .= ")";

        return $where;
    }

// injects extra sql for file attachments search
    static function PostsSearch($sql)
    {
        global $wp_query, $wpdb;

        if (empty($sql)) return $sql;

        wpfb_loadclass('File');

        $is_wp_search = !empty($_GET['s']) && empty($_GET['wpfb_s']);

        $no_matches = false;
        $where = self::SearchWhereSql(false);

        $where = "($where AND (" . WPFB_File::GetReadPermsWhere() . "))";

        // check if there are matching files, if there are, include the filebrowser page/post in the resulst!
        // if we have file pages, only include the file browser if file search widget was used!
        $file_browser_id = intval(WPFB_Core::$settings->file_browser_post_id);
        if ($file_browser_id > 0 && WPFB_File::GetNumFiles2($where, true) > 0 ) {
            $where = "($where OR ({$wpdb->posts}.ID = $file_browser_id))"; // TODO!
            wpfb_loadclass('Output');
            WPFB_Core::$file_browser_search = true;
        }

        // OR' the $where to existing search conditions
        $p = strpos($sql, "(");
        $sql = substr($sql, 0, $p) . "( " . substr($sql, $p);

        $p = strrpos($sql, ")))");
        $sql = substr($sql, 0, $p + 3) . " OR $where)" . substr($sql, $p + 3);

        //echo "<br>".$sql;

        return $sql;
    }

    static function PostsGroupBy($groupby)
    {
        global $wpdb;
        if (!empty($groupby)) $groupby .= ", ";
        $groupby .= "{$wpdb->posts}.ID";
        return $groupby;
    }

    // ID3Join method removed for PHP 8.0+ compatibility

// used for filebrowser search results
    static function FileSearchContent(&$ref_content)
    {
        wpfb_loadclass('ListTpl');
        $tpl = WPFB_ListTpl::Get(WPFB_Core::$settings->search_result_tpl);
        if ($tpl !== null) {
            $ref_content .= $tpl->Generate(null, array('page_limit' => WPFB_Core::$settings->filelist_num
            ));
        } else {
            $files = WPFB_File::GetFiles2(self::SearchWhereSql(false, sanitize_text_field(stripslashes($_GET['wpfb_s']))), WPFB_Core::$settings->hide_inaccessible);
            foreach ($files as $file)
                $ref_content .= $file->GenTpl2();
        }
    }


    static function addFilepagePermSql($sql)
    {
        //echo "add to $sql ";
        //if (empty($sql)) $sql = " 1=1";
        wpfb_loadclass('File');
        return $sql . " AND (post_type <> 'wpfb_filepage' OR (" . WPFB_File::GetReadPermsWhere() . "))";
    }
}