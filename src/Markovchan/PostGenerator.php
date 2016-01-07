<?php

namespace Markovchan;

abstract class PostGenerator
{
    public function generate()
    {
        $board = isset($_GET['board']) ? $_GET['board'] : 'g';
        $seed = isset($_GET['seed']) ? $_GET['seed'] : (int) microtime(true);

        $pdo_db = DatabaseConnection::openForReading($board);

        $permalink = "?seed={$seed}";

        srand($seed);

        $cached_words = [];

        $post_words = [];
        $previous_word = '\x02'; // Signifies start of text
        do {
            $next_word = self::getNextWord($previous_word, $cached_words, $pdo_db, $board);
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

        $metadata = self::compileMetadata($board, $pdo_db);
        $formatted_metadata = '';
        foreach ($metadata as $type => $value) {
            $formatted_metadata .= "$type $value";
        }

        return <<<HTML
            <!DOCTYPE html>
            <html>
                <head>
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <title>/$board/</title>
                    <link href="style.css" rel="stylesheet">
                </head>
                <body class="$color_scheme">
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

    /**
     * Compile metadata about a board
     */
    protected function compileMetadata($board, $pdo_db)
    {
        $metadata = [];

        $post_count_sel = "SELECT COUNT(*) FROM {$board}_processed_post";
        $ppcs_statement = $pdo_db->prepare($post_count_sel);
        $ppcs_statement->execute();

        $metadata['processed_post_count'] = $ppcs_statement->fetchColumn();

        return $metadata;
    }

    /**
     * Get the next word for the chain
     */
    protected function getNextWord($previous_word, &$cached_words, $pdo_db, $board)
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
            $selection_statement = $pdo_db->prepare($word_selection);
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
}
