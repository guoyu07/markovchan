<?php
/**
 * Functions for both index.php and parse.php
 *
 * PHP version 5
 *
 * @category Markovchan
 * @package  Markovchan
 * @author   Oliver Vartiainen <firoxer@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     https://github.com/firoxer/markovchan
 */

/**
 * Compiles metadata about a board
 *
 * @param string $board Target baord
 * @param PDO    $db    Database connection
 *
 * @return array The metadata
 */
function compile_metadata($board, $db)
{
    $metadata = [];

    $processed_post_count_selection = <<<SQL
        SELECT COUNT(*)
        FROM {$board}_processed_post
SQL;

    $ppcs_statement = $db->prepare($processed_post_count_selection);
    $ppcs_statement->execute();

    $metadata['processed_post_count'] = $ppcs_statement->fetchColumn();

    return $metadata;
}

/**
 * Retrieve JSON from on URL
 *
 * @param string $url Target URL
 *
 * @return string Retrieved JSON
 */
function get_json_from_url($url)
{
    sleep(1); // As to not upset the API

    $r = new http\Client\Request('GET', $url);
    $c = new http\Client();
    $c->enqueue($r)->send();

    return json_decode($c->getResponse()->getBody());
}

/**
 * Fetch thread metadata as JSON from a board
 *
 * @param string $board Target board
 *
 * @return string Thread metadata as JSON
 */
function get_raw_threads_from_api($board)
{
    return get_json_from_url("http://a.4cdn.org/{$board}/threads.json");
}

/**
 * Extract thread numbers from raw thread JSON
 *
 * @param string $raw_threads Raw threads as JSON
 *
 * @return array List of thread numbers
 */
function extract_numbers_from_raw_threads($raw_threads)
{
    $thread_numbers = [];
    foreach ($raw_threads as $page) {
        foreach ($page->threads as $thread) {
            $thread_numbers[] = $thread->no;
        }
    }

    return $thread_numbers;
}

/**
 * Turn a thread number into a full thread as JSON
 *
 * @param string $board  Target board
 * @param int    $number Thread number
 *
 * @return string Full thread JSON
 */
function get_raw_thread_from_api_by_number($board, $number)
{
    return get_json_from_url("http://a.4cdn.org/{$board}/thread/{$number}.json");
}

/**
 * Turn raw thread JSON into posts
 *
 * @param string $thread Thread as JSON
 *
 * @return array List of posts by their number
 */
function extract_thread_posts($thread)
{
    $thread_posts = [];
    foreach ($thread->posts as $post) {
        if (isset($post->com)) { // Has text
            $contents = $post->com;
            $contents = str_replace('<br>', ' \n ', $contents);
            $contents = strip_tags($contents);

            $thread_posts[$post->no] = $contents;
        }
    }

    return $thread_posts;
}

/**
 * Split text into an array of its words, paired 1-2, 2-3, 3-4...
 *
 * @param string $text Text
 *
 * @return array (2D) array of word pairs
 */
function split_text_to_word_pairs($text)
{
    echo "<pre>{$text}</pre>";
    $text = preg_replace('/([.,?!:;]) /', ' \1 ', $text);
    $text = preg_replace('/([.,?!:;])\n/', ' \1 ', $text);
    $text = preg_replace('/([.,?!:;])$/', ' \1 ', $text);
    $text = trim($text);
    $split_text = preg_split('/ +/', $text);

    $first_set = array_merge(['\x02'], $split_text);
    $second_set = array_merge($split_text, ['\x03']);

    $pairs = [];
    for ($i = 0; $i < count($split_text) + 1; $i += 1) {
        $pairs[] = [$first_set[$i], $second_set[$i]];
    }

    return $pairs;
}

/**
 * Open the database connection
 *
 * @return PDO Database connection
 */
function open_database_connection()
{
    return new PDO('sqlite:/tmp/markovchan.db', null, null);
}

/**
 * Prepare the database for writing
 *
 * @param string $board The name of the board whose data to write
 *
 * @return PDO Returns the database or dies with an error message
 */
function initialize_database_for_writing($board)
{
    $db = open_database_connection();

    $word_pair_table = "{$board}_word_pair";
    $processed_post_table = "{$board}_processed_post";

    try {
        $word_pair_table_exists = $db->prepare("SELECT 1 FROM $word_pair_table LIMIT 1");
        $processed_post_table_exists = $db->prepare("SELECT 1 FROM $processed_post_table LIMIT 1");

        if (!$word_pair_table_exists) {
            $word_pair_table_creation = <<<SQL
                CREATE TABLE $word_pair_table (
                    word_a varchar(64) not null,
                    word_b varchar(64) not null,
                    matches int(10) not null,
                    UNIQUE (word_a, word_b)
                )
SQL;
            $table_creation_statement = $db->prepare($word_pair_table_creation);
            $table_creation_ok = $table_creation_statement->execute();

            // No need to check for the other table in either case
            if ($table_creation_ok) {
                return $db;
            } else {
                die('Could not create table in the database');
            }
        }

        if (!$processed_post_table_exists) {
            $table_creation = <<<SQL
                CREATE TABLE $processed_post_table (
                    number int(11) not null,
                    PRIMARY KEY (number)
                )
SQL;
            $table_creation_statement = $db->prepare($table_creation);
            $table_creation_ok = $table_creation_statement->execute();

            // No need to check for the other table in either case
            if ($table_creation_ok) {
                return $db;
            } else {
                die('Could not create table in the database');
            }
        } else {
            if (is_table_ok_for_writing($word_pair_table, $db)
                && is_table_ok_for_writing($processed_post_table, $db)
            ) {
                return $db;
            };
        }
    } catch (Exception $_e) {
        die('Error trying to connect to the database');
    }
}

/**
 * Prepare the database for reading
 *
 * @param string $board The name of the board whose data to read
 *
 * @return PDO Returns the database or dies with an error message
 */
function initialize_database_for_reading($board)
{
    $db = open_database_connection();

    $table = "{$board}_word_pair";

    try {
        $board_exists = $db->prepare("SELECT 1 FROM $table LIMIT 1");

        if (!$board_exists) {
            $table_creation = <<<SQL
                CREATE TABLE $table (
                    word_a varchar(64) not null,
                    word_b varchar(64) not null,
                    matches int(10) not null,
                    UNIQUE word_a, word_b
                )
SQL;
            $table_creation_statement = $db->prepare($table_creation);
            $table_creation_ok = $table_creation_statement->execute();

            if ($table_creation_ok) {
                die('The database is now initialized but empty. Parse some posts first!');
            } else {
                die('Could not create table in the database');
            }
        } else {
            if (is_table_ok_for_reading($table, $db)) {
                return $db;
            };
        }
    } catch (Exception $_e) {
        die('Error trying to connect to the database');
    }
}
/**
 * Tests if a table is good for reading
 *
 * @param string $table Name of the table
 * @param PDO    $db    Database connection
 *
 * @return boolean Returns true if OK, dies with an error message otherwise
 */
function is_table_ok_for_reading($table, $db)
{
    $test_selection = "SELECT COUNT(*) FROM $table LIMIT 1";

    $test_selection_statement = $db->prepare($test_selection);
    $test_selection_statement->execute();
    $test_ok = $test_selection_statement->fetchColumn() != 0;

    if ($test_ok) {
        return true;
    } else {
        die('There is no data. Parse some posts first!');
    }
}

/**
 * Tests if a table is good for writing
 *
 * @param string $table Name of the table
 * @param PDO    $db    Database connection
 *
 * @return boolean Returns true if OK, dies with an error message otherwise
 */
function is_table_ok_for_writing($table, $db)
{
    $test_selection = "SELECT 1 FROM $table LIMIT 1";

    $test_selection_statement = $db->prepare($test_selection);
    $test_ok = $test_selection_statement->execute();

    if ($test_ok) {
        return true;
    } else {
        die("Table \"{$table}\" is not good for writing");
    }
}

/**
 * Get the next word for the chain
 *
 * @param string $previous_word Previous chained word
 * @param array  $cached_words  Word cache for quicker reference
 * @param PDO    $db            Database connection
 * @param string $board         The board used
 *
 * @return string A suitable word
 */
function get_next_word($previous_word, &$cached_words, $db, $board)
{
    if (in_array($previous_word, array_keys($cached_words))) {
        $next_word_candidates = $cached_words[$previous_word];
    } else {
        $word_selection = <<<SQL
            SELECT word_b, matches
            FROM {$board}_word_pair
            WHERE word_a = :word
            ORDER BY matches DESC
SQL;
        $selection_statement = $db->prepare($word_selection);
        $selection_statement->execute([':word' => $previous_word]);

        $next_word_candidates = [];
        while ($next_word_row = $selection_statement->fetch()) {
            for ($i = 0; $i < $next_word_row['matches']; $i += 1) {
                $next_word_candidates[] = $next_word_row['word_b'];
            }
        }

        if (!isset($cached_words[$previous_word])) {
            $cached_words[$previous_word] = $next_word_candidates;
        }
    }

    $random_word = $next_word_candidates[array_rand($next_word_candidates) / 2 + count($next_word_candidates) / 2];

    return $random_word;
}
