<?php

declare(strict_types = 1);

namespace Markovchan;

use PDO;

class ApiParser
{
    const THREAD_API_HOST = 'http://a.4cdn.org';
    const THUMB_API_HOST = 'http://i.4cdn.org';
    const IMAGE_API_HOST = 'http://t.4cdn.org';

    const PAGE_MIN = 1;
    const PAGE_MAX = 10;

    const GOOD_FILE_EXTS = ['.jpg', '.png'];

    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function parse(string $board): array
    {
        $threads = $this->getThreads($board);
        if (empty($threads)) {
            // API is not responding or something else is screwed
            return false;
        }

        $image_data = $this->extractRandomImageUrls($threads, $board);
        $all_posts = $this->extractPosts($threads);
        $fresh_posts = $this->dropOldPosts($all_posts, $board);
        $insertion_ok = $this->insertPostsToDatabase($fresh_posts, $board);

        return [
            'image_data' => $image_data,
            'success' => $insertion_ok,
        ];
    }

    private function dropOldPosts(array $all_posts, string $board): array
    {
        $post_numbers_group = implode('\',\'', array_keys($all_posts));
        $selection_sql = <<<SQL
            SELECT number
            FROM {$board}_processed_post
            WHERE number IN ('$post_numbers_group')
SQL;

        $selection_stmt = $this->db->prepare($selection_sql);
        $selection_stmt->execute();

        $fresh_posts = $all_posts;
        while ($old_number_row = $selection_stmt->fetch()) {
            if (isset($fresh_posts[$old_number_row['number']])) {
                unset($fresh_posts[$old_number_row['number']]);
            }
        }

        return $fresh_posts;
    }

    private function extractPosts(array $threads): array
    {
        $pick_threads_posts = function ($all_posts, $thread) {
            $these_posts = $this->extractThreadPosts($thread);
            $all_posts = array_merge($all_posts, $these_posts);
            return $all_posts;
        };
        $posts = array_reduce($threads, $pick_threads_posts, []);

        return $posts;
    }

    private function extractRandomImageUrls(array $threads, string $board): array
    {
        shuffle($threads);

        foreach ($threads as $thread) {
            $posts = $thread['posts'];
            shuffle($posts);

            foreach ($posts as $post) {
                if (!isset($post['filename']) || !in_array($post['ext'], self::GOOD_FILE_EXTS)) {
                    continue;
                }

                $image_size = $post['fsize'] > 1024 * 1024
                    ? round($post['fsize'] / 1024 / 1024, 2) . ' MB'
                    : round($post['fsize'] / 1024) . ' KB';

                return [
                    'image_name' => $post['filename'] . $post['ext'],
                    'image_size' => $image_size,
                    'image_resolution' => "{$post['w']}x{$post['h']}",
                    'image_url' => self::IMAGE_API_HOST . "/$board/{$post['tim']}{$post['ext']}",
                    'thumb_url' => self::THUMB_API_HOST . "/$board/{$post['tim']}s.jpg",
                ];
            }
        }

        return '';
    }

    /**
     * Turn raw thread JSON into posts
     */
    private function extractThreadPosts(array $thread): array
    {
        $thread_posts = [];
        foreach ($thread['posts'] as $post) {
            if (isset($post['com'])) { // Has text
                $contents = $post['com'];
                $contents = str_replace('<br>', ' \n ', $contents);
                $contents = strip_tags($contents);

                $thread_posts['#' . $post['no']] = $contents;
            }
        }

        return $thread_posts;
    }

    /**
     * Retrieve JSON from an URL
     */
    private function getJson(string $url): array
    {
        $client = new \Guzzle\Http\Client;
        $request = $client->get($url);
        $response = $request->send();

        return $response->json();
    }

    /**
     * Fetch thread metadata as JSON from a board
     */
    private function getThreads(string $board): array
    {
        $page_id = rand(self::PAGE_MIN, self::PAGE_MAX);
        $response_json = $this->getJson(self::THREAD_API_HOST . "/$board/$page_id.json");
        return empty($response_json) ? [] : $response_json['threads'];
    }

    private function insertPostsToDatabase(array $fresh_posts, string $board): bool
    {
        $this->db->beginTransaction();

        foreach ($fresh_posts as $number => $post) {
            $insertion_sql = "INSERT INTO {$board}_processed_post (number) VALUES (:number)";
            $insertion_stmt = $this->db->prepare($insertion_sql);
            $insertion_stmt->execute([':number' => $number]);

            $pairs = $this->splitTextToPairs($post);

            foreach ($pairs as $pair) {
                $insertion_sql = <<<SQL
                    INSERT OR IGNORE INTO {$board}_word_pair
                        (word_a, word_b, matches)
                    VALUES
                        (:word_a, :word_b, 0)
SQL;

                $insertion_stmt = $this->db->prepare($insertion_sql);
                $insertion_stmt->execute(
                    [':word_a' => $pair[0], ':word_b' => $pair[1]]
                );

                $update_sql = <<<SQL
                    UPDATE {$board}_word_pair
                    SET matches = matches + 1
                    WHERE word_a = :word_a
                          AND word_b = :word_b
SQL;

                $update_stmt = $this->db->prepare($update_sql);
                $update_stmt->execute(
                    [':word_a' => $pair[0], ':word_b' => $pair[1]]
                );
            }
        }

        $commit_ok = $this->db->commit();
        return $commit_ok;
    }

    /**
     * Split text into an array of its words, paired 1-2, 2-3, 3-4...
     */
    private function splitTextToPairs(string $text): array
    {
        $text = preg_replace('/([.,?!:;]) /', ' \1 ', $text);
        $text = preg_replace('/([.,?!:;])\n/', ' \1 ', $text);
        $text = preg_replace('/([.,?!:;])$/', ' \1 ', $text);
        $text = trim($text);
        $split_text = preg_split('/ +/', $text);

        $first_set = array_merge([PostGenerator::START_OF_POST], $split_text);
        $second_set = array_merge($split_text, [PostGenerator::END_OF_POST]);

        $pairs = [];
        for ($i = 0; $i < count($split_text) + 1; $i += 1) {
            $pairs[] = [$first_set[$i], $second_set[$i]];
        }

        return $pairs;
    }
}
