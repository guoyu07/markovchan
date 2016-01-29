# Markovchan

Markovchan is a text analyzer and generator for 4chan posts. It is based on Markov chains.

## Basic usage

Run `parse.php` a couple of times (preferably >10 000) to gather data, then see `index.php` for the results. Source for posts is /g/ by default but can be overridden with `?board=x` in either file.

## Installation

### Requisites

* PHP7.0
* SQLite3 and its bindings for PHP
* PHP curl extension
* Composer

### Installation

0. Install all the requisites
0. Run `composer install` in the project directory
0. Create a file `/tmp/markovchan.sqlite` and allow PHP to read and write to it

## Why did you code it

To learn Markov chains, some basic language processing and PHP7.
