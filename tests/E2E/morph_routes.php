<?php

declare(strict_types=1);

require __DIR__ . '/morph_models.php';

use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Database\Relations\MorphTo;
use TondbadSwoole\Http\Response;

return function (Route $route): void {
    $route->get('/setup', function (Response $response): void {
        $pdo = db()->connection('sqlite');

        $pdo->statement('drop table if exists comments');
        $pdo->statement('drop table if exists posts');
        $pdo->statement('drop table if exists videos');

        $pdo->statement('create table posts (id integer primary key autoincrement, title text)');
        $pdo->statement('create table videos (id integer primary key autoincrement, title text)');
        $pdo->statement('create table comments (id integer primary key autoincrement, body text, commentable_type text, commentable_id integer)');

        $pdo->statement("insert into posts (title) values ('Hello Post')");
        $postId = (int) $pdo->lastInsertId();

        $pdo->statement("insert into videos (title) values ('Hello Video')");
        $videoId = (int) $pdo->lastInsertId();

        $pdo->statement("insert into comments (body, commentable_type, commentable_id) values ('First','post',{$postId})");
        $pdo->statement("insert into comments (body, commentable_type, commentable_id) values ('Second','video',{$videoId})");
        $pdo->statement("insert into comments (body, commentable_type, commentable_id) values ('Third','post',null)");

        $response->end('OK');
    });

    $route->get('/morph-has', function (Response $response): void {
        MorphTo::morphMap(['post' => Post::class, 'video' => Video::class]);

        $comments = Comment::query()->has('commentable')->get();

        $response->json(array_map(fn (Comment $c) => $c->getAttribute('body'), $comments));
    });

    $route->get('/morph-where-has', function (Response $response): void {
        MorphTo::morphMap(['post' => Post::class, 'video' => Video::class]);

        $comments = Comment::query()->whereHas('commentable', function ($query) {
            $query->where('title', '=', 'Hello Post');
        })->get();

        $response->json(array_map(fn (Comment $c) => $c->getAttribute('body'), $comments));
    });

    $route->get('/morph-doesnt-have', function (Response $response): void {
        MorphTo::morphMap(['post' => Post::class, 'video' => Video::class]);

        $comments = Comment::query()->doesntHave('commentable')->get();

        $response->json(array_map(fn (Comment $c) => $c->getAttribute('body'), $comments));
    });
};
