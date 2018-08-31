<?php

$db = new SPDO('sqlite:'.__DIR__ . DIRECTORY_SEPARATOR . 'database'. DIRECTORY_SEPARATOR .'db_001.sqlite');
$tableName = 'item';
$items = $db->select('*', $tableName);



if ($_POST) {
    $description = $_POST['description'];
    $method = $_POST['method'];
    $id = (int) $_POST['id'];
    $result = false;

    switch ($method) {
        case 'save':
            if ($id === 0) {
                $result = $db->insert($tableName, [
                    'description' => htmlspecialchars($description)
                ]);

                print($db->getError() ? ['error' => $db->getError()] : json_encode(['id' => $result]));

            } else {
                $result = $db->update($tableName, [
                    'description' => htmlspecialchars($description)
                ], 'id = ?', [$id]);

                print($db->getError() ? ['error' => $db->getError()] : json_encode(['id' => $id]));
            }
            break;

        case 'remove':
            if ($id > 0) {
                $result = $db->delete($tableName, 'id = ?', [$id]);
                print($db->getError() ? ['error' => $db->getError()] : json_encode(['ok' => true]));
            }
            break;

        case 'get':
            if (!empty($items)) {
                foreach($items as $index => $item) {
                    $items[$index]['description'] = htmlspecialchars_decode( $items[$index]['description'] );
                }

                print(json_encode((array) $items));
            }
            break;

        default:
    }

    exit;
}
