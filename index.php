<?php
/**
 * The "read" part of markovchan
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

$board = isset($_GET['board']) ? $_GET['board'] : 'g';
$seed = isset($_GET['seed']) ? $_GET['seed'] : (int) microtime(true);

$db = initialize_database_for_reading($board);

$permalink = "?seed={$seed}";

srand($seed);

$cached_words = [];

$post_words = [];
$previous_word = '\x02'; // Signifies start of text
do {
    $next_word = get_next_word($previous_word, $cached_words, $db, $board);
    $post_words[] = $next_word;
    $previous_word = $next_word;
} while ($next_word != '\x03'); // Signifies end of text

array_pop($post_words); // Remove the excess \x03

$raw_post = implode(' ', $post_words);
$raw_post_by_line = explode('\n ', $raw_post);

$cooked_post_by_line = [];
foreach ($raw_post_by_line as $line) {
    if (preg_match('/^&gt;(?!&gt;)/', $line)) {
        $line = "<span class='greentext'>$line</span>";
    }

    $line = preg_replace('/ ([.,?!:;])/', '\1', $line);
    $line = preg_replace('/ &gt;&gt;(\d+)/', ' <a href="#">&gt;&gt;\1</a>', $line);
    $line = preg_replace('/^&gt;&gt;(\d+)/', '<a href="#">&gt;&gt;\1</a>', $line);

    $cooked_post_by_line[] = $line;
}

$formatted_post = implode('<br>', $cooked_post_by_line);
$date = date('m/d/y(D)H:i:s', $seed);
$post_number = rand(50000000, 59999999);
$color_scheme = in_array($board, ['g']) ? 'yotsuba_b' : 'yotsuba';

?>

<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>/<?php echo $board ?>/</title>
        <link href="style.css" rel="stylesheet">
    </head>
    <body class="<?php echo $color_scheme ?>">
        <div id="post_wrapper">
            <section class="post">
                <header class="post_header">
                    <input type="checkbox">
                    <span class="post_author">Anonymous</span>
                    <?php echo $date ?>
                    <nav>
                        <a href="<?php echo $permalink ?>">No.</a>
                        <a href="/"><?php echo $post_number ?></a>
                    </nav>
                </header>
                <div class="post_content">
                    <?php echo $formatted_post ?>
                </div>
            </section>
        </div>
    </body>
</html>
