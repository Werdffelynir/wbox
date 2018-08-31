<?php

include 'system/bootstrap.php';
include 'system/index.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>W-Dox</title>
    <link rel="stylesheet" href="css/grid.css">
    <link rel="stylesheet" href="css/main.css">

    <script src="js/namespaceapplication.js"></script>
    <script src="js/main.js"></script>
</head>
<body>
    <div id="app">
        <div id="header">
            <button id="button-add" class="button">Add</button>
        </div>
        <div id="content">
            <?php if($items): foreach ($items as $item): ?>

                <div class="item" data-id="<?=$item['id']?>">
                    <div class="item-panel">
                        <span class="btn-save ">Save</span>
                        <span class="btn-comment color-violet">Add Comment</span>
                        <span class="btn-remove color-red">Remove</span>
                        <span class="text-right">SAVED</span>
                    </div>
                    <?=htmlspecialchars_decode($item['description']);?>
                </div>

            <?php endforeach; endif;?>
        </div>
        <div id="footer"></div>
    </div>
    <?php include 'template.php'?>
</body>
</html>