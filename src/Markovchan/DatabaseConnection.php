<?php

declare(strict_types = 1);

namespace Markovchan;

use PDO;

abstract class DatabaseConnection
{
    const DB_PATH = '/tmp/markovchan.db';

    /**
     * Prepare the database for reading
     */
    public function openForReading(string $board): PDO
    {
        $pdo_db = self::openRaw();

        $table = "{$board}_word_pair";
        $board_exists = $pdo_db->prepare("SELECT 1 FROM $table LIMIT 1");

        if (!$board_exists) {
            $creation_sql = <<<SQL
                CREATE TABLE $table (
                    word_a varchar(64) not null,
                    word_b varchar(64) not null,
                    matches int(10) not null,
                    UNIQUE (word_a, word_b)
                )
SQL;

            $creation_stmt = $pdo_db->prepare($creation_sql);
            $creation_ok = $creation_stmt->execute();

            if ($creation_ok) {
                throw new \RuntimeException('The database is initialized but empty');
            } else {
                throw new \RuntimeException('Could not create table in the database');
            }
        } elseif (self::isTableUsable($table, $pdo_db)) {
            return $pdo_db;
        } else {
            throw new \RuntimeException('Table exists but is unreadable');
        };
    }

    /**
     * Prepare the database for writing
     */
    public function openForWriting(string $board): PDO
    {
        $pdo_db = self::openRaw();

        $pair_table = "{$board}_word_pair";
        $post_table = "{$board}_processed_post";

        $pair_table_exists = $pdo_db->prepare("SELECT 1 FROM $pair_table LIMIT 1");
        $post_table_exists = $pdo_db->prepare("SELECT 1 FROM $post_table LIMIT 1");

        if (!$pair_table_exists) {
            $creation_sql = <<<SQL
                CREATE TABLE $pair_table (
                    word_a varchar(64) not null,
                    word_b varchar(64) not null,
                    matches int(10) not null,
                    UNIQUE (word_a, word_b)
                )
SQL;
            $creation_stmt = $pdo_db->prepare($creation_sql);
            $creation_ok = $creation_stmt->execute();

            // No need to check for the other table in either case
            if ($creation_ok) {
                return $pdo_db;
            } else {
                throw new \RuntimeException('Could not create table in the database');
            }
        }

        if (!$post_table_exists) {
            $creation_sql = <<<SQL
                CREATE TABLE $post_table (
                    number varchar(16) not null,
                    PRIMARY KEY (number)
                )
SQL;
            $creation_stmt = $pdo_db->prepare($creation_sql);
            $creation_ok = $creation_stmt->execute();

            // No need to check for the other table in either case
            if ($creation_ok) {
                return $pdo_db;
            } else {
                throw new \RuntimeException('Could not create table in the database');
            }
        } elseif (self::isTableUsable($pair_table, $pdo_db)
                && self::isTableUsable($post_table, $pdo_db)) {
            return $pdo_db;
        }
    }

    /**
     * Tests if a table is good for reading
     */
    protected function isTableUsable(string $table, PDO $pdo_db): bool
    {
        $selection_sql = "SELECT COUNT(*) FROM $table LIMIT 1";

        $selection_stmt = $pdo_db->prepare($selection_sql);
        $selection_stmt->execute();
        $selection_ok = $selection_stmt->fetchColumn() != 0;

        return $selection_ok;
    }

    /**
     * Open the database connection
     */
    protected function openRaw(): PDO
    {
        return new PDO('sqlite:' . self::DB_PATH, null, null);
    }
}
