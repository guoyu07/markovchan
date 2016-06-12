<?php

declare(strict_types = 1);

namespace Markovchan;

use PDO;

use Twig_Loader_Array;
use Twig_Environment;

abstract class PostGenerator
{
    const START_OF_POST = '\x02';
    const END_OF_POST = '\x03';

    const FAUX_POST_NUMBER_MIN = 50000000;
    const FAUX_POST_NUMBER_MAX = 59999999;

    const POST_TEMPLATE = <<<HTML
        <!DOCTYPE html>
        <html>
            <head>
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>/{{ board }}/</title>
                <link href="/style.css" rel="stylesheet">
            </head>
            <body class="{{ color_scheme }}">
                <nav>
                    [<a href="a">a</a> / <a href="b">b</a> / <a href="c">c</a> / <a href="d">d</a> / <a href="e">e</a> / <a href="f">f</a> / <a href="g">g</a> / <a href="gif">gif</a> / <a href="h">h</a> / <a href="hr">hr</a> / <a href="k">k</a> / <a href="m">m</a> / <a href="o">o</a> / <a href="p">p</a> / <a href="r">r</a> / <a href="s">s</a> / <a href="t">t</a> / <a href="u">u</a> / <a href="v">v</a> / <a href="vg">vg</a> / <a href="vr">vr</a> / <a href="w">w</a> / <a href="wg">wg</a>] [<a href="i">i</a> / <a href="ic">ic</a>] [<a href="r9k">r9k</a>] [<a href="s4s">s4s</a>] [<a href="cm">cm</a> / <a href="hm">hm</a> / <a href="lgbt">lgbt</a> / <a href="y">y</a>] [<a href="3">3</a> / <a href="aco">aco</a> / <a href="adv">adv</a> / <a href="an">an</a> / <a href="asp">asp</a> / <a href="biz">biz</a> / <a href="cgl">cgl</a> / <a href="ck">ck</a> / <a href="co">co</a> / <a href="diy">diy</a> / <a href="fa">fa</a> / <a href="fit">fit</a> / <a href="gd">gd</a> / <a href="hc">hc</a> / <a href="his">his</a> / <a href="int">int</a> / <a href="jp">jp</a> / <a href="lit">lit</a> / <a href="mlp">mlp</a> / <a href="mu">mu</a> / <a href="n">n</a> / <a href="news">news</a> / <a href="out">out</a> / <a href="po">po</a> / <a href="pol">pol</a> / <a href="sci">sci</a> / <a href="soc">soc</a> / <a href="sp">sp</a> / <a href="tg">tg</a> / <a href="toy">toy</a> / <a href="trv">trv</a> / <a href="tv">tv</a> / <a href="vp">vp</a> / <a href="wsg">wsg</a> / <a href="x">x</a>]
                </nav>
                <div id="post_wrapper">
                    <section class="post">
                        <header class="post_header">
                            <input type="checkbox">
                            <span class="post_author">Anonymous</span>
                            {{ date }}
                            No. {{ post_number }}
                        </header>
                        {% if image_url %}
                            <div class="image_info">
                                File: <a href="{{ image_url }}">{{ image_name }}</a> ({{ image_size }}, {{ image_resolution }})
                            </div>
                            <div class="image_wrapper">
                                <a href="{{ image_url }}">
                                    <img src="{{ thumb_url }}">
                                </a>
                            </div>
                        {% endif %}
                        <div class="post_content">
                            {{ final_post|raw }}
                        </div>
                    </section>
                </div>
                <footer>
                    {{ formatted_metadata }}
                </footer>
            </body>
        </html>
HTML;

    public static function generate(string $board, PDO $pdo_db, array $image_data, array $ext_metadata = []): string
    {
        $cached_words = [];

        $post_words = [];
        $prev_word = self::START_OF_POST; // Signifies start of text
        do {
            $next_word = self::getNextWord($prev_word, $cached_words, $pdo_db, $board);
            $post_words[] = $next_word;
            $prev_word = $next_word;
        } while ($next_word != self::END_OF_POST); // Signifies end of text

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
        $final_post = preg_replace('/<br>(<br>)+/', '<br><br>', $formatted_post);

        $date = date('m/d/y(D)H:i:s');
        $post_number = rand(self::FAUX_POST_NUMBER_MIN, self::FAUX_POST_NUMBER_MAX);
        $color_scheme = in_array($board, ['g']) ? 'yotsuba_b' : 'yotsuba';

        $metadata = self::gatherAndCompileMetadata($ext_metadata, $board, $pdo_db);
        $formatted_metadata = self::formatMetaData($metadata);

        $twig_loader = new Twig_Loader_Array(['index.html' => self::POST_TEMPLATE]);
        $twig = new Twig_Environment($twig_loader);

        $template_data = array_merge($image_data, [
            'board' => $board,
            'color_scheme' => $color_scheme,
            'date' => $date,
            'final_post' => $final_post,
            'formatted_metadata' => $formatted_metadata,
            'post_number' => $post_number,
        ]);

        return $twig->render('index.html', $template_data);
    }

    protected static function formatMetadata(array $metadata): string
    {
        $formatted_metadata = [];
        foreach ($metadata as $type => $value) {
            $formatted_metadata[] = "$type: $value";
        }

        $output = implode(', ', $formatted_metadata);

        return $output;
    }

    /**
     * Compile metadata about a board
     */
    protected static function gatherAndCompileMetadata(array $metadata, string $board, PDO $pdo_db): array
    {
        $post_count_sel = "SELECT COUNT(*) FROM {$board}_processed_post";
        $ppcs_statement = $pdo_db->prepare($post_count_sel);
        $ppcs_statement->execute();

        $metadata['processed_post_count'] = $ppcs_statement->fetchColumn();

        return $metadata;
    }

    /**
     * Get the next word for the chain
     */
    protected static function getNextWord(string $prev_word, array &$cached_words, PDO $pdo_db, string $board): string
    {
        if (in_array($prev_word, array_keys($cached_words))) {
            $next_word_candidates = $cached_words[$prev_word];
        } else {
            $word_selection = <<<SQL
                SELECT word_b, matches
                FROM {$board}_word_pair
                WHERE word_a = :word
                ORDER BY matches DESC
SQL;
            $selection_statement = $pdo_db->prepare($word_selection);
            $selection_statement->execute([':word' => $prev_word]);

            $next_word_candidates = [];
            while ($next_word_row = $selection_statement->fetch()) {
                for ($i = 0; $i < $next_word_row['matches']; $i += 1) {
                    $next_word_candidates[] = $next_word_row['word_b'];
                }
            }

            if (!isset($cached_words[$prev_word])) {
                $cached_words[$prev_word] = $next_word_candidates;
            }
        }

        $random_word = $next_word_candidates[array_rand($next_word_candidates) / 2 + count($next_word_candidates) / 2];

        return $random_word;
    }
}
