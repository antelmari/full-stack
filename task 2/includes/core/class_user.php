<?php

class User
{

    // GENERAL


    public static function user_info($d = [])
    {
        // vars
        $user_id = isset($d['user_id']) && is_numeric((int)$d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        $plots_ids = Plot::all_plots_list();
        // where
        if ($user_id) $where = "user_id='" . $user_id . "'";
        else if ($phone) $where = "phone='" . $phone . "'";
        else return [
            'user_id' => 0,
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'users_plots_ids' => '',
            'all_plots_ids' => $plots_ids,
        ];
        // info
        $q = DB::query("SELECT user_id, first_name, last_name, email, phone, access FROM users WHERE " . $where . " LIMIT 1;") or die(DB::error());
        if ($row = DB::fetch_row($q)) {
            $users_plots_ids = self::get_users_plots($user_id);
            return [
                'user_id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'users_plots_ids' => implode(', ', $users_plots_ids),
                'all_plots_ids' => $plots_ids,
                'access' => $row['access'],
            ];
        } else {
            return [
                'user_id' => 0,
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone' => '',
                'users_plots_ids' => '',
                'all_plots_ids' => $plots_ids,
            ];
        }
    }

    public static function users_list($d = [])
    {
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = isset($d['limit']) && is_numeric($d['limit']) ? $d['limit'] : 20;
        $items = [];
        // where
        $where = [];
        if ($search) $where[] = "(first_name LIKE '%" . $search . "%' OR last_name LIKE '%" . $search . "%' OR phone LIKE '%" . $search . "%')";
        $where = $where ? "WHERE " . implode(" AND ", $where) : "";
        // info
        $q = DB::query("SELECT user_id, village_id, first_name, last_name, email, phone, phone_code, phone_attempts_code, phone_attempts_sms, updated, last_login
            FROM users " . $where . " ORDER BY user_id LIMIT " . $offset . ", " . $limit . ";") or die(DB::error());
        while ($row = DB::fetch_row($q)) {
            $users_plots_ids = self::get_users_plots($row['user_id']);
            $items[] = [
                'user_id' => (int) $row['user_id'],
                'users_plots_ids' => implode(', ', $users_plots_ids),
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => phone_formatting($row['phone']),
                'email' => $row['email'],
                'last_login' => date('Y/m/d', $row['last_login']),
            ];
        }
        // paginator
        $q = DB::query("SELECT count(*) FROM users " . $where . ";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search=' . $search;
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function users_list_plots($number)
    {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT `users`.`user_id`, `users`.`first_name`, `users`.`email`, `users`.`phone`, `users_plots`.`user_id`, `users_plots`.`plot_id` 
            FROM `users` JOIN `users_plots` ON(`users`.`user_id`=`users_plots`.`user_id`) WHERE `users_plots`.`plot_id`=" . $number . " ORDER BY `users_plots`.`user_id`;") or die(DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function users_fetch($d = [])
    {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }



    // ACTIONS

    public static function user_edit_window($d = [])
    {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info(['user_id' => $user_id]));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = [])
    {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $first_name = isset($d['first_name']) ? $d['first_name'] : 0;
        $last_name = isset($d['last_name']) ? $d['last_name'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        $email = isset($d['email']) ? $d['email'] : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        $plots_ids = isset($d['plots_ids']) ? $d['plots_ids'] : '';
        // update
        if ($user_id) {
            $set = [];
            $set[] = "first_name='" . $first_name . "'";
            $set[] = "last_name='" . $last_name . "'";
            $set[] = "phone='" . $phone . "'";
            $set[] = "email='" . $email . "'";
            $set[] = "updated='" . Session::$ts . "'";
            $set = implode(", ", $set);
            DB::query("UPDATE users SET " . $set . " WHERE user_id='" . $user_id . "' LIMIT 1;") or die(DB::error());

            self::update_users_plots(["user_id" => $user_id, "plots_ids" => $plots_ids]);
        } else {
            // create
            DB::query("INSERT INTO users (
                first_name,
                last_name,
                phone,
                email,
                updated
            ) VALUES (
                '" . $first_name . "',
                '" . $last_name . "',
                '" . $phone . "',
                '" . $email . "',
                '" . Session::$ts . "'
            );") or die(DB::error());
            self::update_users_plots(["user_id" => DB::connect()->lastInsertId('users'), "plots_ids" => $plots_ids]);
        }
        // output
        return User::users_fetch(['offset' => $offset]);
    }

    public static function user_delete($d = [])
    {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        // delete
        if ($user_id) {
            DB::query("DELETE FROM users WHERE user_id='" . $user_id . "' LIMIT 1;");
        }
        return User::users_fetch(['offset' => $offset]);
    }

    // PLOTS

    public static function get_users_plots($user_id)
    {
        $user_id = isset($user_id) && is_numeric($user_id) ? $user_id : 0;
        $q = DB::query("SELECT plot_id FROM users_plots WHERE user_id=" . $user_id . " ORDER BY plot_id ASC;");
        $plots_ids = [];
        while ($row = DB::fetch_row($q)) {
            $plots_ids[] = $row['plot_id'];
        }
        return $plots_ids;
    }

    public static function update_users_plots($d = [])
    {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? (int) $d['user_id'] : 0;
        if (!isset($d['plots_ids'])) die("Error: 'plots_ids' is not defined");
        $new_plots = str_replace(' ', '', $d['plots_ids']);
        $new_plots = array_unique(explode(',', $new_plots));
        $values = [];

        DB::query("DELETE FROM users_plots WHERE user_id={$user_id};") or die(DB::error());
        if ($new_plots[0] != "") {
            foreach ($new_plots as $plot) {

                $values[] = "(" . $user_id . "," . $plot . ")";
            }

            DB::query("INSERT INTO users_plots (user_id, plot_id) VALUES " . implode(", ", $values));
        }
    }
}
