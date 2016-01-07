<?php

namespace Markovchan;

abstract class ApiParser
{
    public function parse(string $board): bool
    {
        $pdo_db = DatabaseConnection::openForWriting($board);

        $threads = self::getThreads($board);
        $posts = self::extractThreadPosts($threads[1]);

        $post_numbers_group = implode(',', array_keys($posts));
        $selection_sql = <<<SQL
            SELECT number
            FROM {$board}_processed_post
            WHERE number IN ($post_numbers_group)
SQL;

        $selection_stmt = $pdo_db->prepare($selection_sql);
        $selection_stmt->execute();

        $fresh_posts = $posts;
        while ($old_number_row = $selection_stmt->fetch()) {
            if (isset($fresh_posts[$old_number_row['number']])) {
                unset($fresh_posts[$old_number_row['number']]);
            }
        }

        $pdo_db->beginTransaction();

        foreach ($fresh_posts as $number => $post) {
            $insertion_sql = "INSERT INTO {$board}_processed_post (number) VALUES (:number)";
            $insertion_stmt = $pdo_db->prepare($insertion_sql);
            $insertion_stmt->execute([':number' => $number]);

            $pairs = self::splitTextToPairs($post);

            foreach ($pairs as $pair) {
                $insertion_sql = <<<SQL
                    INSERT OR IGNORE INTO {$board}_word_pair
                        (word_a, word_b, matches)
                    VALUES
                        (:word_a, :word_b, 0)
SQL;

                $insertion_stmt = $pdo_db->prepare($insertion_sql);
                $insertion_stmt->execute(
                    [':word_a' => $pair[0], ':word_b' => $pair[1]]
                );

                $update_sql = <<<SQL
                    UPDATE {$board}_word_pair
                    SET matches = matches + 1
                    WHERE word_a = :word_a
                          AND word_b = :word_b
SQL;

                $update_stmt = $pdo_db->prepare($update_sql);
                $update_stmt->execute(
                    [':word_a' => $pair[0], ':word_b' => $pair[1]]
                );
            }
        }

        $pdo_db->commit();

        return true;
    }

    /**
     * Turn raw thread JSON into posts
     */
    protected function extractThreadPosts($thread)
    {
        $thread_posts = [];
        foreach ($thread['posts'] as $post) {
            if (isset($post['com'])) { // Has text
                $contents = $post['com'];
                $contents = str_replace('<br>', ' \n ', $contents);
                $contents = strip_tags($contents);

                $thread_posts[$post['no']] = $contents;
            }
        }

        return $thread_posts;
    }

    /**
     * Retrieve JSON from an URL
     */
    protected function getJson($url)
    {
        sleep(1); // As to not upset the API

        $client = new \Guzzle\Http\Client;
        $request = $client->get($url);
        $response = $request->send();

        return json_decode($response->getBody(), true);
    }

    /**
     * Fetch thread metadata as JSON from a board
     */
    protected function getThreads($board)
    {
        return self::getJson("http://a.4cdn.org/{$board}/1.json")['threads'];
    }

    /**
     * Split text into an array of its words, paired 1-2, 2-3, 3-4...
     */
    protected function splitTextToPairs($text)
    {
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
}
