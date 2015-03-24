<?php

class Advanced_ORM extends Kohana_ORM {

  public function __construct($id = NULL)
  {
    Kohana::$profiling === TRUE && $bm = Profiler::start('ORM', __FUNCTION__);

    parent::__construct($id);

    $this->after_initialize();

    isset($bm) && Profiler::stop($bm);
  }

  protected $_errors = [];

  public function errors()
  {
    return $this->_errors;
  }

  public function try_create(Validation $validation = NULL)
  {
    try
    {
      $this->create($validation);
      return TRUE;
    }
    catch (ORM_Validation_Exception $ex)
    {
      $this->_errors = $ex->errors('models');
    }
    return FALSE;
  }

  public function try_update(Validation $validation = NULL)
  {
    try
    {
      $this->update($validation);
      return TRUE;
    }
    catch (ORM_Validation_Exception $ex)
    {
      $this->_errors = $ex->errors('models');
    }
    return FALSE;
  }


  public function build($model, array $values = NULL)
  {
    $column = Inflector::plural($model);

    $col = Arr::get($this->_has_many, $column);

    $foreign_key = Arr::get($col, 'foreign_key');
    $polymorphic = Arr::get($col, 'polymorphic', FALSE);

    $object = ORM::factory($model);

    if ($polymorphic && $object instanceof ORM_Polymorph)
    {
      $foreign_key = $object->polymorph_id();
      $foreign_type = $object->polymorph_type();

      $values[$foreign_type] = $this->object_name();
    }

    $values[$foreign_key] = $this->id;

    return $object->values($values);
  }



  public function get($column)
  {
    if (array_key_exists($column, $this->_object))
    {
      return parent::get($column);
    }
    elseif (isset($this->_related[$column]))
    {
      // Return related model that has already been fetched
      return $this->_related[$column];
    }
    elseif (isset($this->_belongs_to[$column]))
    {
      return parent::get($column);
    }
    elseif (isset($this->_has_one[$column]))
    {
      return parent::get($column);
    }
    elseif (isset($this->_has_many[$column]))
    {
      $model = ORM::factory($this->_has_many[$column]['model']);

      if (isset($this->_has_many[$column]['through']))
      {
        // Grab has_many "through" relationship table
        $through = $this->_has_many[$column]['through'];

        // Join on through model's target foreign key (far_key) and target model's primary key
        $join_col1 = $through.'.'.$this->_has_many[$column]['far_key'];
        $join_col2 = $model->_object_name.'.'.$model->_primary_key;

        $model->join($through)->on($join_col1, '=', $join_col2);

        // Through table's source foreign key (foreign_key) should be this model's primary key
        $col = $through.'.'.$this->_has_many[$column]['foreign_key'];
        $val = $this->pk();
      }
      else
      {
        if (Arr::get($this->_has_many[$column], 'polymorphic', FALSE) === TRUE && $model instanceof ORM_Polymorph)
        {
          $col = $model->_object_name.'.'.$model->polymorph_id();
          $val = $this->pk();
          $model->where($model->polymorph_type(), '=', $this->_object_name);
        }
        else
        {
          // Simple has_many relationship, search where target model's foreign key is this model's primary key
          $col = $model->_object_name.'.'.$this->_has_many[$column]['foreign_key'];
          $val = $this->pk();
        }
      }

      return $model->where($col, '=', $val);
    }
    else
    {
      return parent::get($column);
    }
  }


  /**
   * Default LIMIT, usefull for big data
   * @var integer
   */
  protected $_limit = 1000;

  public function find_all()
  {
    $this->_check_scope_deleted();
    $this->_check_scope_published();
    $result = parent::find_all();
    $this->after_find();
    return $result;
  }

  public function count_all()
  {
    $this->_check_scope_deleted();
    $this->_check_scope_published();
    return parent::count_all();
  }

  public function find()
  {
    $this->_check_scope_deleted();
    $this->_check_scope_published();
    $result = parent::find();
    $this->after_find();
    return $result;
  }


  /**
   * Paginating
   */
  
  /**
   * Paginator object
   * @var Advanced_Paginator
   */
  public $paginator = NULL;

  protected $_count          = 0;
  protected $_count_per_page = 10;
  protected $_page_get_param = 'page';

  /**
   * Find with pagination
   * @param  int $count_per_page Items per page
   * @param  int $page_get_param $_GET param
   * @return Database_Result
   */
  public function paginate($count_per_page = NULL, $page_get_param = NULL)
  {
    if ($this->paginator === NULL)
    {
      $clone        = clone $this;
      $this->_count = $clone->count_all();

      if ($count_per_page !== NULL)
      {
        $this->_count_per_page = max(1, $count_per_page);
      }

      if ($page_get_param !== NULL)
      {
        $this->_page_get_param = $page_get_param;
      }

      $this->paginator = new Paginator($this->_count, $this->_count_per_page, $this->_page_get_param);
    }

    return $this->find_all();
  }

  protected function _build($type)
  {
    parent::_build($type);

    if ($type === Database::SELECT)
    {
      if ($this->paginator !== NULL)
      {
        $this->_db_builder->limit($this->paginator->count_per_page())->offset($this->paginator->current_offset());
      }
      else if ( ! isset($this->_db_applied['limit']))
      {
        if ( ! empty($this->_limit))
        {
          $this->_db_builder->limit($this->_limit);
        }
      }

    }

    return $this;
  }


  /**
   * Soft deleting
   */

  protected $_deleted_column = NULL;

  protected $_force_deleting = FALSE;

  public function deleted_column()
  {
    return $this->_deleted_column;
  }

  public function delete()
  {
    $this->before_delete();

    if (is_array($this->_deleted_column))
    {
      if ( ! $this->_loaded)
      {
        throw new Kohana_Exception('Cannot delete :model model because it is not loaded.', [':model' => $this->_object_name]);
      }

      $column = $this->_deleted_column['column'];
      $format = $this->_deleted_column['format'];

      $this->values([$column => ($format === TRUE) ? time() : date($format)])->update();

      return $this->reset(TRUE)->clear();
    }

    parent::delete();

    $this->update_count();

    $this->after_delete();

    return $this;
  }

  public function force_delete()
  {
    $this->_force_deleting = TRUE;
    $this->delete();
    $this->_force_deleting = FALSE;
  }

  protected $_with_deleted = FALSE;

  public function with_deleted()
  {
    $this->_with_deleted = TRUE;
    return $this;
  }

  public function only_deleted()
  {
    if ($this->_deleted_column !== NULL)
    {
      $this->with_deleted()->where($this->_deleted_column['column'], 'IS', NULL);
    }
    return $this;
  }

  public function is_deleted()
  {
    if ($this->_deleted_column !== NULL)
    {
      return $this->{$this->_deleted_column['column']} !== NULL;
    }
  }

  public function restore()
  {
    if ($this->_deleted_column !== NULL)
    {
      if ( ! $this->_loaded)
      {
        throw new Kohana_Exception('Cannot restore :model model because it is not loaded.', [':model' => $this->_object_name]);
      }

      $column = $this->_deleted_column['column'];

      $this->values([$column => NULL])->update();
    }

    return $this;
  }

  protected function _check_scope_deleted()
  {
    if ( ! $this->_with_deleted && is_array($this->_deleted_column))
    {
      $this->where($this->_deleted_column['column'], 'IS', NULL);
    }
    return $this;
  }




  /**
   * Publishing
   */

  protected $_published_column = NULL;

  public function published_column()
  {
    return $this->_published_column;
  }

  public function publish()
  {
    // $this->before_publish();

    if (is_array($this->_published_column))
    {
      if ( ! $this->_loaded)
      {
        throw new Kohana_Exception('Cannot publish :model model because it is not loaded.', [':model' => $this->_object_name]);
      }

      $column = $this->_published_column['column'];
      $format = $this->_published_column['format'];

      $this->values([$column => ($format === TRUE) ? time() : date($format)])->save();

      return $this->reset(TRUE)->clear();
    }

    $this->update_count();

    // $this->after_publish();

    return $this;
  }

  protected $_with_published = TRUE;

  public function with_unpublished()
  {
    $this->_with_published = FALSE;
    return $this;
  }

  public function only_unpublished()
  {
    if ($this->_published_column !== NULL)
    {
      $this->with_unpublished()->where($this->_published_column['column'], 'IS', NULL);
    }
    return $this;
  }

  public function is_published()
  {
    if ($this->_published_column !== NULL)
    {
      return $this->{$this->_published_column['column']} !== NULL;
    }
  }

  public function unpublish()
  {
    if ($this->_published_column !== NULL)
    {
      if ( ! $this->_loaded)
      {
        throw new Kohana_Exception('Cannot unpublish :model model because it is not loaded.', [':model' => $this->_object_name]);
      }

      $column = $this->_published_column['column'];

      $this->values([$column => NULL])->update();
    }

    return $this;
  }

  protected function _check_scope_published()
  {
    if ($this->_with_published && is_array($this->_published_column))
    {
      $this->where($this->_published_column['column'], 'IS NOT', NULL);
      $this->where($this->_published_column['column'], '<=', date('Y-m-d H:i:s'));
    }
    return $this;
  }



  /**
   * Updates all existing records
   *
   * @return ORM
   */
  public function update_all()
  {
    $this->_build(Database::UPDATE);

    if (empty($this->_changed))
    {
      // Nothing to update
      return $this;
    }

    $data = array();
    foreach ($this->_changed as $column)
    {
      // Compile changed data
      $data[$column] = $this->_object[$column];
    }

    if (is_array($this->_updated_column))
    {
      // Fill the updated column
      $column = $this->_updated_column['column'];
      $format = $this->_updated_column['format'];

      $data[$column] = $this->_object[$column] = ($format === TRUE) ? time() : date($format);
    }

    $this->_db_builder->set($data)->execute($this->_db);

    return $this;
  }

  /**
   * Delete all objects in the associated table. This does NOT destroy
   * relationships that have been created with other objects.
   */
  public function delete_all()
  {
    if (is_array($this->_deleted_column))
    {
        $column = $this->_deleted_column['column'];
        $format = $this->_deleted_column['format'];
 
        return $this->values([$column => ($format === TRUE) ? time() : date($format)])->update_all();
    }

    $this->_build(Database::DELETE);

    $this->_db_builder->execute($this->_db);

    return $this->clear();
  }



  /**
   * Pseudo functions
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
   * @return mixed
   */
  public function __call($method_name, $args)
  {
    if (preg_match('#^(?<action>find_or_initialize|find_or_create|find_all|find|count_all)_by_(?:(?<field1>\w+)_and_(?<field2>\w+)|(?<field>\w+))$#', $method_name, $matches))
    {
      $field1 = null;
      $field2 = null;

      if (isset($matches['field']))
      {
        $field1 = $matches['field'];
      }

      if ( ! $field1)
      {
        $field1 = $matches['field1'];
        $field2 = $matches['field2'];
      }

      $value1 = Arr::get($args, 0);
      $value2 = Arr::get($args, 1);

      $op1 = is_array($value1) ? 'IN' : '=';
      $op2 = is_array($value2) ? 'IN' : '=';

      $this->where($this->_object_name . '.' . $field1, $op1, $value1);

      if ($field2)
      {
        $this->where($this->_object_name . '.' . $field2, $op2, $value2);
      }

      switch ($matches['action'])
      {
      case 'find': return $this->find();

      case 'find_all': return $this->find_all();

      case 'count_all': return $this->count_all();

      case 'find_or_initialize':
      case 'find_or_create':
        $this->find();

        if ( ! $this->loaded())
        {
          $this->$field1 = $value1;

          if ($field2)
          {
            $this->$field2 = $value2;
          }

          if ($matches['action'] == 'find_or_create')
          {
            $this->save();
          }
        }

        break;
      }

      return $this;
    }
    elseif (preg_match('#^(?<action>where|or_where|and_where)_(?<field>\w+)_(?<condition>contains|starts_with|ends_with|is|like|equals?|before|after|eq|gte?|lte?|not|in)$#', $method_name, $matches))
    {
      $value = Arr::get($args, 0);

      if ( ! isset($value) || empty($value))
      {
        return $this;
      }

      $field = $matches['field'];

      if ($value instanceof ORM)
      {
        $value = $value->$field;
      }

      $op = 'LIKE';

      switch ($matches['condition'])
      {
        case 'contains': $value = '%' . $value . '%'; break;
        case 'starts_with': $value = $value . '%'; break;
        case 'ends_with': $value = '%' . $value; break;
        case 'before': $op = '<'; break;
        case 'lt': $op = '<'; break;
        case 'lte': $op = '<='; break;
        case 'after': $op = '>'; break;
        case 'gt': $op = '>'; break;
        case 'gte': $op = '>='; break;
        case 'not': $op = '!='; break;
        case 'in': $op = 'IN'; $value = (array)$value; break;
      }

      return $this->{$matches['action']}($matches['field'], $op, $value);
    }
    elseif (preg_match('#^with_(?<field>\w+)$#', $method_name, $matches))
    {
      $value = Arr::get($args, 0);

      if ( ! isset($value) || empty($value))
      {
        return $this;
      }

      $op = is_array($value) ? 'IN' : '=';

      return $this->where($matches['field'], $op, $value);
    }
  }


  /**
   * Proxy to `find_all()` method
   * @return Database_Result
   */
  public function all()
  {
    return $this->find_all();
  }

  /**
   * Proxy to `find()` method
   * @return ORM
   */
  public function first()
  {
    return $this->find();
  }


  public function rand($uniq = '')
  {
    return $this->order_by(DB::Expr('RAND(' . $uniq . ')'));
  }

  public function as_json()
  {
    return json_encode($this->as_array(), JSON_PP);
  }



  /**
   * Quick actions (inc, dec, etc)
   */

  public function dec($field, $amount = 1)
  {
    $this->{$field} -= $amount;
    $this->update();
    return $this;
  }

  public function inc($field, $amount = 1)
  {
    $this->{$field} += $amount;
    $this->update();
    return $this;
  }



  /**
   * Performs as DB transaction for a sequence of queries
   * @param  Closure $closure A block with queries passed as a function
   * @return void
   * @throws Exception If transaction fails
   */
  public function transaction(Closure $closure)
  {
    $this->_db->begin();
    try
    {
      $closure();
      $this->_db->commit();
    }
    catch (Exception $ex)
    {
      $this->_db->rollback();
      throw $ex;
    }
  }

  /**
   * Count caching
   *
   * These functions update `related_count` column to parent object
   *
   * For example:
   * We have got Model_Article and Model_Comment,
   * so a Model_Article has many Model_Comment objects.
   * If it got a `comments_count` column, we can cache count of comments 
   * to this column on each change to comments.
   */

  public function update_count()
  {
    $count_column = $this->_object_plural . '_count';
    foreach ($this->_belongs_to as $col => $related)
    {
      $model = $this->{$col};
      if ($model->loaded() && array_key_exists($count_column, $model->table_columns()))
      {
        $model->values([$count_column => $model->{$this->_object_plural}->count_all()])->update();
      }
    }
  }


  /**
   * Callbacks
   */

  public function create(Validation $validation = NULL)
  {
    $this->before_save();
    $this->before_create();
    parent::create($validation);
    $this->update_count();
    $this->after_create();
    $this->after_save();
    return $this;
  }

  public function update(Validation $validation = NULL)
  {
    $this->before_save();
    $this->before_update();

    $need_count = FALSE;

    if ($this->_deleted_column !== NULL && $this->changed($this->_deleted_column['column']))
    {
      $need_count = TRUE;
    }

    if ($this->_published_column !== NULL && $this->changed($this->_published_column['column']))
    {
      $need_count = TRUE;
    }

    parent::update($validation);

    if ($need_count === TRUE)
    {
      $this->update_count();
    }

    $this->after_update();
    $this->after_save();
    return $this;
  }

  public function check(Validation $validation = NULL)
  {
    $this->before_validation();
    parent::check($validation);
    $this->after_validation();
    return $this;
  }

  protected function before_validation()
  {
    // Nothing by default
  }

  protected function after_validation()
  {
    // Nothing by default
  }

  protected function before_save()
  {
    // Nothing by default
  }

  protected function after_save()
  {
    // Nothing by default
  }

  protected function before_create()
  {
    // Nothing by default
  }

  protected function after_create()
  {
    // Nothing by default
  }

  protected function before_delete()
  {
    // Nothing by default
  }

  protected function after_delete()
  {
    // Nothing by default
  }

  protected function after_initialize()
  {
    // Nothing by default
  }

  protected function after_find()
  {
    // Nothing by default
  }


}
