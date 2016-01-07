<?php

namespace Markovchan;

abstract class PostGenerator
{
    public function generate()
    {
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

        $metadata = compile_metadata($board, $db);
        $formatted_metadata = '';
        foreach ($metadata as $type => $value) {
            $formatted_metadata .= "$type $value";
        }

        return <<<HTML
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
                                $date
                                <nav>
                                    <a href="$permalink">No.</a>
                                    <a href="/">$post_number ?></a>
                                </nav>
                            </header>
                            <div class="post_content">
                                $formatted_post
                            </div>
                        </section>
                    </div>
                    <footer>
                        $formatted_metadata
                    </footer>
                </body>
            </html>
HTML;
    }
}
