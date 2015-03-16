<?php

/**
 * Proxying model to ORM with static calls
 *
 * Suppose, you have a model:
 *
 *    class Model_User extends ORM { }
 *
 * Then, you just create an InitModel:
 *
 *    class User extends InitModel { }
 *
 * And now, you may call like this:
 *
 *    User::init(10); // get an existing user with ID = 10
 *    User::create(['name' => 'John', 'lastname' => 'Doe', 'age' => 20]); // create a new user
 *    User::paginate(25); // find all user paginated by 25 users per page
 *
 * Combined flow:
 *
 *    User::where('status', '=', 'ACTIVE')->find_all();
 *    User::where('city', '=', 'Irkutsk')->paginate(50);
 *    
 * @package   Advanced_ORM
 * @author    Alexei Shabalin <mail@alshabalin.com>
 */
abstract class InitModel {

  /**
   * Proxy to `ORM::factory()`
   * @param  mixed $id id or conditions
   * @return ORM
   */
  public static function init($id = NULL)
  {
    return ORM::factory(get_class(new static), $id);
  }

  /**
   * Proxy to `Advanced_ORM::paginate()`
   * @param  int $count_per_page Item per page
   * @param  int $page_get_param $_GET param with page pointer
   * @return Database_Result Paginated results
   */
  public static function paginate($count_per_page = NULL, $page_get_param = NULL)
  {
    return ORM::factory(get_class(new static))->paginate($count_per_page, $page_get_param);
  }

  /**
   * An alias for `find_all()` method
   * @return Database_Result
   */
  public static function all()
  {
    return ORM::factory(get_class(new static))->find_all();
  }

  /**
   * Proxy to `ORM::find_all()`
   * @return Database_Result
   */
  public static function find_all()
  {
    return ORM::factory(get_class(new static))->find_all();
  }

  /**
   * Proxy to `ORM::find()`
   * @return ORM
   */
  public static function find()
  {
    return ORM::factory(get_class(new static))->find();
  }

  /**
   * An alias for `find()` method
   * @return ORM
   */
  public static function first()
  {
    return ORM::factory(get_class(new static))->find();
  }

  /**
   * Proxy to `ORM::factory()` with $conditions
   * @param  mixed $conditions Conditions in array
   * @return ORM
   */
  public static function find_by($conditions = NULL)
  {
    return ORM::factory(get_class(new static), $conditions);
  }

  /**
   * Proxy to `ORM::where()`
   * @param  string $column Column name
   * @param  string $op     Operator (=, IS, LIKE, IN, etc)
   * @param  int|string $value  Column value
   * @return ORM
   */
  public static function where($column, $op, $value)
  {
    return ORM::factory(get_class(new static))->where($column, $op, $value);
  }

  /**
   * Proxy to `ORM::count_all()`
   * @return int
   */
  public static function count_all()
  {
    return ORM::factory(get_class(new static))->count_all();
  }

  /**
   * Proxy to `ORM::values()`. This method should be used to initialize a new ORM object.
   * 
   * @param  array      $values   Array of column => val
   * @param  array|null $expected Array of keys to take from `$values`
   * @return ORM
   */
  public static function values(array $values, array $expected = NULL)
  {
    return ORM::factory(get_class(new static))->values($values, $expected);
  }

  /**
   * Proxy to `ORM::values()`. Initialize and create (do save) a new ORM object.
   * 
   * @param  array      $values   Array of column => val
   * @param  array|null $expected Array of keys to take from `$values`
   * @return ORM
   */
  public static function create(array $values, array $expected = NULL)
  {
    return ORM::factory(get_class(new static))->values($values, $expected)->create();
  }

  /**
   * Proxy to `ORM::unique()`
   * 
   * @param   string   $field  the field to check for uniqueness
   * @param   mixed    $value  the value to check for uniqueness
   * @return  bool     whteher the value is unique
   */
  public static function unique($field, $value)
  {
    return ORM::factory(get_class(new static))->unique($field, $value);
  }

  /**
   * Proxy to `ORM::table_name()`
   * @return string
   */
  public static function table_name()
  {
    return ORM::factory(get_class(new static))->table_name();
  }

  /**
   * Proxy to `ORM::table_columns()`
   * @return array
   */
  public static function table_columns()
  {
    return ORM::factory(get_class(new static))->table_columns();
  }

  /**
   * Proxy to `ORM::distinct()`. Enables or disables selecting only unique columns using "SELECT DISTINCT"
   *
   * @param   boolean  $value  enable or disable distinct columns
   * @return  $this
   */
  public static function distinct($value)
  {
    return ORM::factory(get_class(new static))->distinct($value);
  }

  /**
   * Proxy to `ORM::cached()`
   * @param  int $lifetime Lifetime in seconds
   * @return ORM
   */
  public static function cached($lifetime = NULL)
  {
    return ORM::factory(get_class(new static))->cached($lifetime);
  }

  /**
   * Proxy to `Advanced_ORM::with_deleted()`
   * @return ORM
   */
  public static function with_deleted()
  {
    return ORM::factory(get_class(new static))->with_deleted();
  }

  /**
   * Proxy to `Advanced_ORM::only_deleted()`
   * @return ORM
   */
  public static function only_deleted()
  {
    return ORM::factory(get_class(new static))->only_deleted();
  }

  /**
   * Proxy to `ORM::limit()`
   * @param  int $number maximum results to return 
   * @return ORM
   */
  public static function limit($number)
  {
    return ORM::factory(get_class(new static))->limit($number);
  }

  /**
   * Proxy to `ORM::order_by()`
   * @param   mixed   $column     column name or array($column, $alias) or object
   * @param   string  $direction  direction of sorting
   * @return  ORM
   */
  public static function order_by($column, $direction = NULL)
  {
    return ORM::factory(get_class(new static))->order_by($column, $direction = NULL);
  }

  /**
   * Proxy to `Advanced_ORM::rand()`
   * @param  string $uniq
   * @return ORM
   */
  public static function rand($uniq = '')
  {
    return ORM::factory(get_class(new static))->rand($uniq);
  }

  /**
   * Proxy to `ORM::with()`
   * @param  string $target_path Target model to bind to
   * @return ORM
   */
  public static function with($target_path)
  {
    return ORM::factory(get_class(new static))->with($target_path);
  }


  /**
   * Proxy to pseudo functionsof Advanced_ORM
   *  find_by_*
   *  find_all_by_*
   *  find_or_initialize_by_*
   *  find_or_create_by_*
   *  count_all_by_*
   *
   *  where_*
   *  and_where_*
   *  or_where_*
   *
   * @return ORM|Database_Result|mixed
   */
  public static function __callStatic($method_name, $args)
  {
    return call_user_func_array([ORM::factory(get_class(new static)), $method_name], $args);
  }

}
