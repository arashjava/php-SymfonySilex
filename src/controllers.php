<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addGlobal('user', $app['session']->get('user'));
    // set the per page variable global
    $twig->addGlobal('perPage', $app['session']->get('perPage'));
    // set the last page for pagination variable global
    $twig->addGlobal('lastPage', $app['session']->get('lastPage'));
    // set the current page for pagination variable global
    $twig->addGlobal('curPage', $app['session']->get('curPage'));
    return $twig;
}));


$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html', [
        'readme' => file_get_contents('../README.md'),
    ]);
});


$app->match('/login', function (Request $request) use ($app) {
    $username = $request->get('username');
    $password = $request->get('password');

    if ($username) {
        $sql = "SELECT * FROM users WHERE username = '$username' and password = '$password'";
        $user = $app['db']->fetchAssoc($sql);

        if ($user){
            $app['session']->set('user', $user);
            return $app->redirect('/todo');
        }
    }

    return $app['twig']->render('login.html', array());
});

// save record per page for Todos in Session
$app->match('/todo/perpage/{perPage}', function ($perPage)  use ($app) {
    if ($perPage>0){
        $app['session']->set('perPage', $perPage);
        return $app->redirect('/todo');
    }
 });

$app->get('/logout', function () use ($app) {
 //   $app['session']->set('user', null);
    $app['session']->clear();
    return $app->redirect('/');
});


$app->get('/todo/record/{id}', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }
    if ($id){
        $sql = "SELECT * FROM todos WHERE id = '$id'";
        $todo = $app['db']->fetchAssoc($sql);
        return $app['twig']->render('todo.html', [
            'todo' => $todo,
            ]);
    }
})
->value('id', null);


$app->get('/todo/{page}', function ($page) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }
    //  set number of rows per page
    $perPage=(int)$app['session']->get('perPage');
    if ($perPage<1) $perPage=4;     // considers default row numbers per page = 4
    
    // set page =1 as default
    if (!$page) $page=1;
    
     $sql = "SELECT * FROM todos WHERE user_id = '${user['id']}'";
    // get and set number of records for pagination
    $num_rows = $app['db']->executeQuery($sql)->rowCount();
    $lastPage = ceil($num_rows/$perPage);
    $app['session']->set('lastPage', $lastPage);
    //  save the current page for pagination in session
    if ($page >0) $app['session']->set('curPage', $page);
    
    // record to start with
    $start = ($page>1) ?($page *$perPage)-$perPage:0;   
    //   pagination with using $start, $perPage variables
    $sql = $sql. " Limit $start, $perPage";
    $todos = $app['db']->fetchAll($sql);

    return $app['twig']->render('todos.html', [
        'todos' => $todos,
    ]);
})
->value('page', null);



//  Show the Todo in JSON format
$app->get('/todo/json/{id}', function ($id, $json=NULL) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }
    $sql = "SELECT * FROM todos WHERE id = '$id'";
    $todo = $app['db']->fetchAssoc($sql);
    return $app['twig']->render('todo_json.html', [
            'todo' => json_encode($todo),
            ]);   
})
->value('id', null);

$app->post('/todo/add', function (Request $request) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }
    $user_id = $user['id'];
    $description = $request->get('description');

    // a user can't add a todo without a description
    if ($description !=NULL){
        $sql = "INSERT INTO todos (user_id, description) VALUES ('$user_id', '$description')";
        $app['db']->executeUpdate($sql);
        // set success flash messages
        $app['session']->getFlashBag()->add('success', 'Record is added successfully');
    }
    else{
        // set failure flash messages
        $app['session']->getFlashBag()->add('warning', 'Record is not added!');
    }
     //  to keep the curent page the same when program gets back to user
    $curPage = $app['session']->get('curPage');
    return $app->redirect('/todo/'.$curPage);
});


$app->match('/todo/delete/{id}', function ($id) use ($app) {

    $sql = "DELETE FROM todos WHERE id = '$id'";
    $app['db']->executeUpdate($sql);
    $app['session']->getFlashBag()->add('success', 'Record is deleted successfully');
     //  to keep the curent page the same when program gets back to user
    $curPage = $app['session']->get('curPage');
    return $app->redirect('/todo/'.$curPage);
})
->value('id', null);

// Confirmation that the task is completed / or change it back to not completed
$app->match('/todo/completed/{id}', function ($id) use ($app) {

    $sql = "update todos set completed = !completed WHERE id = '$id'";
    $app['db']->executeUpdate($sql);
    //  to keep the curent page the same when program gets back to user
    $curPage = $app['session']->get('curPage');
    return $app->redirect('/todo/'.$curPage);
})
->value('id', null);