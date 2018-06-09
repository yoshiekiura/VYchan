<?php
	require 'info.php';
	
	function basic_build($action, $settings, $board) {
		// Possible values for $action:
		//	- all (rebuild everything, initialization)
		//	- news (news has been updated)
		//	- boards (board list changed)
		
		Basic::build($action, $settings);
	}
	
	// Wrap functions in a class so they don't interfere with normal Tinyboard operations
	class Basic {
		public static function build($action, $settings) {
			global $config;


            if ($action == 'all' || $action == 'news')
				file_write($config['dir']['home'] . $settings['file'], Basic::homepage($settings));
		}
		
		// Build news page
		public static function homepage($settings) {
			global $config, $board;
			
			$settings['no_recent'] = (int) $settings['no_recent'];
			
			$query = query("SELECT * FROM ``news`` ORDER BY `time` DESC LIMIT 1") or error(db_error());
			$news = $query->fetchAll(PDO::FETCH_ASSOC);


            $recent_images = Array();
            $recent_posts = Array();
            $stats = Array();

            $boards = listBoards();

            $query = '';
            foreach ($boards as &$_board) {
                if (in_array($_board['uri'], []))
                    continue;
                $query .= sprintf("SELECT *, '%s' AS `board` FROM ``posts_%s`` WHERE `files` IS NOT NULL UNION ALL ", $_board['uri'], $_board['uri']);
            }
            $query = preg_replace('/UNION ALL $/', 'ORDER BY `time` DESC LIMIT ' . 4, $query);

            if ($query == '') {
                error(_("Can't build the RecentPosts theme, because there are no boards to be fetched."));
            }

            $query = query($query) or error(db_error());

            while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
                openBoard($post['board']);

                if (isset($post['files']))
                    $files = json_decode($post['files']);

                if ($files[0]->file == 'deleted' || $files[0]->thumb == 'file') continue;

                // board settings won't be available in the template file, so generate links now
                $post['link'] = $config['root'] . $board['dir'] . $config['dir']['res']
                    . link_for($post) . '#' . $post['id'];

                if ($files) {
                    if ($files[0]->thumb == 'spoiler') {
                        $tn_size = @getimagesize($config['spoiler_image']);
                        $post['src'] = $config['spoiler_image'];
                        $post['thumbwidth'] = $tn_size[0];
                        $post['thumbheight'] = $tn_size[1];
                    }
                    else {
                        $post['src'] = $config['uri_thumb'] . $files[0]->thumb;
                        $post['thumbwidth'] = $files[0]->thumbwidth;
                        $post['thumbheight'] = $files[0]->thumbheight;
                    }
                }

                $recent_images[] = $post;
            }


            $query = '';
            foreach ($boards as &$_board) {
                if (in_array($_board['uri'], []))
                    continue;
                $query .= sprintf("SELECT *, '%s' AS `board` FROM ``posts_%s`` UNION ALL ", $_board['uri'], $_board['uri']);
            }
            $query = preg_replace('/UNION ALL $/', 'ORDER BY `time` DESC LIMIT ' . 30, $query);
            $query = query($query) or error(db_error());

            while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
                openBoard($post['board']);

                $post['link'] = $config['root'] . $board['dir'] . $config['dir']['res'] . link_for($post) . '#' . $post['id'];
                if ($post['body'] != "")
                    $post['snippet'] = pm_snippet($post['body'], 30);
                else
                    $post['snippet'] = "<em>" . _("(no comment)") . "</em>";
                $post['board_name'] = $board['name'];

                $recent_posts[] = $post;
            }

            // Total posts
            $query = 'SELECT SUM(`top`) FROM (';
            foreach ($boards as &$_board) {
                if (in_array($_board['uri'], []))
                    continue;
                $query .= sprintf("SELECT MAX(`id`) AS `top` FROM ``posts_%s`` UNION ALL ", $_board['uri']);
            }
            $query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
            $query = query($query) or error(db_error());
            $stats['total_posts'] = number_format($query->fetchColumn());

            // Unique IPs
            $query = 'SELECT COUNT(DISTINCT(`ip`)) FROM (';
            foreach ($boards as &$_board) {
                if (in_array($_board['uri'], []))
                    continue;
                $query .= sprintf("SELECT `ip` FROM ``posts_%s`` UNION ALL ", $_board['uri']);
            }
            $query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
            $query = query($query) or error(db_error());
            $stats['unique_posters'] = number_format($query->fetchColumn());

            // Active content
            $query = 'SELECT DISTINCT(`files`) FROM (';
            foreach ($boards as &$_board) {
                if (in_array($_board['uri'], []))
                    continue;
                $query .= sprintf("SELECT `files` FROM ``posts_%s`` UNION ALL ", $_board['uri']);
            }
            $query = preg_replace('/UNION ALL $/', ' WHERE `num_files` > 0) AS `posts_all`', $query);
            $query = query($query) or error(db_error());
            $files = $query->fetchAll();
            $stats['active_content'] = 0;
            foreach ($files as &$file) {
                preg_match_all('/"size":([0-9]*)/', $file[0], $matches);
                $stats['active_content'] += array_sum($matches[1]);
            }

            $categories = $config['categories'];

            foreach ($categories as &$boards) {
                foreach ($boards as &$board) {
                    $title = boardTitle($board);
                    if (!$title)
                        $title = $board; // board doesn't exist, but for some reason you want to display it anyway
                    $board = Array('title' => $title, 'uri' => sprintf($config['board_path'], $board));
                }
            }

			return Element('themes/basic/index.html', Array(
				'settings' => $settings,
				'config' => $config,
				'boardlist' => createBoardlist(),
				'news' => $news,
				'recent_images' => $recent_images,
				'recent_posts' => $recent_posts,
				'stats' => $stats,
                'categories' => $categories
            ));
		}
	};
	
?>
