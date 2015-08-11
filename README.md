# Markovchan

Markovchan is a text analyzer and generator for 4chan posts. It is based on Markov chains.

## Basic usage

Run parse.php a couple of times (preferably >10 000) to gather data, then see index.php for the results. Source for posts is /g/ by default but can be overridden with `/board=x` in either file.

## Requisites

* PHP5
* SQLite and its bindings for PHP
* pecl_http v2 (easily replaced with something similar though)
