<?php


/**
 * Обертка PDO расширения PHP
 * Class SPDO
 * @package db
 */
class SPDO extends \PDO
{

    /** @var string  */
    private $error;

    /** @var string  */
    private $sql;

    /** @var null|array  */
    private $bind;


    /**
     * Парамеры конструктора аналогичны родителю
     * <pre>
     * Параметры dsn для некоторых серверов:
     * MS SQL Server    "mssql:host=$host;dbname=DATABASE_NAME"
     * Sybase           "sybase:host=$host;dbname=DATABASE_NAME"
     * MySQL            "mysql:host=$host;dbname=DATABASE_NAME"
     * SQLite           "sqlite:my/database/path/DATABASE_FILE"
     * Oracle
     * </pre>
     * @param string    $dsn
     * @param string    $username
     * @param string    $passwd
     * @param array     $options    принимает необходимые атрибуты
     */
    public function __construct($dsn, $username='', $passwd='', array $options=[]) {
        empty($options) AND $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
        ];

        try {
            parent::__construct($dsn, $username, $passwd, $options);
        } catch (\PDOException $e) {
            $this->error = $e->getMessage();
        }

    }


    /**
     * Попытка выполнить запрос. Метод определяет тип операции для выполнения соответстенной задачи
     * <pre>
     * Примеры ниже едентичны, вернут id добавленной записи если драйвер базы данных позволяет
     * ->executeQuery('INSERT INTO folks (name, addr, city) values (?, ?, ?)' ['Mr Doctor', 'some street', 'London']);
     * ->executeQuery('INSERT INTO folks (name, addr, city) values (:name, :addr, :city)' [':name'=>'Mr Doctor', ':addr'=>'some street', ':city'=>'London']);
     *
     * Примеры ниже вернет одну запись
     * ->executeQuery('SELECT name, addr, city FROM folks WHERE city = :city', [':city'=>'London'], false);
     *
     * </pre>
     * @param   string          $sql        Запрос с плейсхолдерами
     * @param   string|array    $bind       Массив значений для биндинга плейсхолдеров
     * @param   bool            $fetchAll   Выбор всех записей или одной.
     * @return  bool|int|array|object       В зависимости от типа запроса и атрибутов \PDO
     */
    public function executeQuery($sql, $bind=null, $fetchAll=true) {
        $this->clear();
        $this->sql  = trim($sql);
        $this->bind = empty($bind) ? null : (array) $bind;

        try {
            $pdoStmt = $this->prepare($this->sql);
            if($pdoStmt->execute($this->bind) !== false)
            {
                $first = strtolower(str_word_count($sql,1)[0]);
                switch($first){
                    case 'select':
                    case 'pragma':
                    case 'describe':
                        if($fetchAll)
                            return $pdoStmt->fetchAll();
                        else
                            return $pdoStmt->fetch();
                    case 'insert':
                        return $this->lastInsertId();
                    case 'update':
                    case 'delete':
                        return $pdoStmt->rowCount();
                    default:
                        return false;
                }
            }
        } catch (\PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }


    /**
     * Попытка выполнить запрос с возвратом одной строки
     *
     * @param string        $sql
     * @param string|array  $bind
     * @return mixed        В зависимости от типа запроса и атрибутов \PDO
     */
    public function executeOne($sql, $bind=null) {
        return $this->executeQuery($sql, $bind, $fetchAll=false);
    }


    /**
     * Попытка выполнить запрос с возвратом множества строк
     *
     * @param string        $sql
     * @param string|array  $bind
     * @return mixed        В зависимости от типа запроса и атрибутов \PDO
     */
    public function executeAll($sql, $bind=null) {
        return $this->executeQuery($sql, $bind, $fetchAll=true);
    }


    /**
     * Возвращает информацию о таблице
     *
     * @param string    $table
     * @return array
     */
    public function tableInfo($table) {
        $driver = $this->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if($driver == 'sqlite') {
            $sql = "PRAGMA table_info('" . $table . "');";
            $key = "name";
        } elseif($driver == 'mysql') {
            $sql = "DESCRIBE " . $table . ";";
            $key = "Field";
        } else {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = '" . $table . "';";
            $key = "column_name";
        }

        if(false !== ($columns = $this->executeQuery($sql))) {
            return $columns;
        }
        return array();
    }


    /**
     * Упрощенный запрос на выборку данных
     *
     * <pre>
     * ->select('id, link, title', 'my_table','active=?', [1]);
     * </pre>
     *
     * @param string    $fields
     * @param string    $table
     * @param string    $where
     * @param string|array $bind
     * @param bool      $fetchAll
     * @return array|bool|int|object
     */
    public function select($fields, $table, $where="", $bind=null, $fetchAll=true) {
        $sql = "SELECT " . $fields . " FROM " . $table;
        if(!empty($where))
            $sql .= " WHERE " . $where;
        $sql .= ";";
        return $this->executeQuery($sql, $bind, $fetchAll);
    }


    /**
     * Упрощенный запрос на запись данных
     *
     * <pre>
     * Пример выполнет запрос и вернет lastInsertId если возможно.
     * ->insert('my_table', ['link'=>'some link', 'title'=>'some title']);
     * </pre>
     *
     * @param string    $table      имя таблицы
     * @param array     $columnData параметры
     * @return int                  Возвращает количество модифицированных строк
     */
    public function insert($table, array $columnData) {
        $columns = array_keys($columnData);
        $sql = sprintf("INSERT INTO %s (%s) VALUES (%s);",
            ' `' . $table . '` ',
            ' `' . implode('`, `', $columns) . '` ',
            implode(', ', array_fill(0, count($columnData), '?'))
        );
        return $this->executeQuery($sql, array_values($columnData));
    }


    /**
     * Упрощенный запрос на удаление данных
     *
     * <pre>
     * Пример выполнет запрос и вернет lastInsertId если возможно.
     * ->delete('my_table', 'id = ?', [123]);
     * </pre>
     *
     * @param string    $table
     * @param string    $where
     * @param string    $bind
     * @return int                  Возвращает количество модифицированных строк
     */
    public function delete($table, $where, $bind=null) {
        $sql = "DELETE FROM " . $table . " WHERE " . $where . ";";
        return $this->executeQuery($sql, $bind);
    }


    /**
     * Упрощенный запрос на обновление данных.
     * Обратить внимание что placeholders в данном запросе должны быть только безымянными (WHERE id = ?, title = ?),
     * но сли в запрос попадет именование placeholders, они будут перегенерированы в безымянные что может привести
     * к непредсказуемым результатам. Переобразование идет по очередно, и если позиции различны запрос будет искажен.
     *
     * <pre>
     * ->update('my_table', ['link'=>'new link', 'title'=>'new title'], 'id = ?', [123]);
     * // Выполнит сгенерированый SQL запрос 'UPDATE my_table SET ('link'=?, 'title'=?) WHERE id = ?'
     * </pre>
     *
     * @param string    $table
     * @param array     $columnData
     * @param string    $where
     * @param string|array $bind
     * @return int                  Возвращает количество модифицированных строк
     */
    public function update($table, array $columnData, $where, $bind=null) {
        $columns = array_keys($columnData);
        $where = preg_replace('|:\w+|','?', $where);
        if(empty($bind))
            $bind = array_values($columnData);
        else
            $bind = array_values(array_merge($columnData, (array) $bind));
        $sql = sprintf("UPDATE %s SET %s WHERE %s;",
            ' `' . $table . '` ',
            ' `' . implode('`=?, `', $columns) . '` = ? ',
            $where
        );
        return $this->executeQuery($sql, $bind);
    }


    private function clear() {
        $this->error = null;
        $this->bind = null;
        $this->sql = null;
    }


    /**
     * Вывод ошибки если есть. Можно использувать на определение ошибки
     * @param bool|string $row can take params: error, sql or bind, default false
     * @return array|bool
     */
    public function getError($row=false) {
        if(!empty($this->error)) {
            $eData = [
                'error'=>$this->error,
                'sql'=>$this->sql,
                'bind'=>$this->bind
            ];
            if(isset($eData[$row]))
                return $eData[$row];
            return $eData;
        }else
            return false;
    }

}