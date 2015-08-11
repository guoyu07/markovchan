<?php
/**
 * The "write" part of markovchan
 *
 * PHP version 5
 *
 * @category Markovchan
 * @package  Markovchan
 * @author   Oliver Vartiainen <firoxer@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     https://github.com/firoxer/markovchan
 */

require 'functions.php';

$index = isset($_GET['index']) ? $_GET['index'] : 5;
$board = isset($_GET['board']) ? $_GET['board'] : 'g';

$db = initialize_database_for_writing($board);

$raw_threads = get_raw_threads($board);
$thread_numbers = extract_numbers_from_raw_threads($raw_threads);

$thread = get_raw_thread_from_number($board, $thread_numbers[$index]);
$posts = extract_thread_posts($thread);

$post_numbers_group = implode(',', array_keys($posts));
$moldy_number_selection = <<<SQL
    SELECT number
    FROM {$board}_processed_post
    WHERE number IN ($post_numbers_group)
SQL;

$selection_statement = $db->prepare($moldy_number_selection);
$selection_statement->execute();

$fresh_posts = $posts;
while ($moldy_number_row = $selection_statement->fetch()) {
    if (isset($fresh_posts[$moldy_number_row['number']])) {
        unset($fresh_posts[$moldy_number_row['number']]);
    }
}

$db->beginTransaction();

foreach ($fresh_posts as $number => $post) {
    $number_insertion = <<<SQL
        INSERT INTO {$board}_processed_post
            (number)
        VALUES
            (:number)
SQL;

    $insertion_statement = $db->prepare($number_insertion);
    $insertion_statement->execute([':number' => $number]);

    $pairs = split_text_to_word_pairs($post);

    foreach ($pairs as $pair) {
        $pair_insertion = <<<SQL
            INSERT OR IGNORE INTO {$board}_word_pair
                (word_a, word_b, matches)
            VALUES
                (:word_a, :word_b, 0)
SQL;

        $insertion_statement = $db->prepare($pair_insertion);
        $insertion_statement->execute(
            [':word_a' => $pair[0], ':word_b' => $pair[1]]
        );

        $pair_update = <<<SQL
            UPDATE {$board}_word_pair
            SET matches = matches + 1
            WHERE word_a = :word_a
                  AND word_b = :word_b
SQL;

        $update_statement = $db->prepare($pair_update);
        $update_statement->execute(
            [':word_a' => $pair[0], ':word_b' => $pair[1]]
        );
    }
}

$db->commit();
