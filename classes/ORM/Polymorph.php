<?php

class ORM_Polymorph extends ORM {

  protected $_polymorph = NULL;

  public function polymorph_id()
  {
    return $this->_polymorph . '_id';
  }

  public function polymorph_type()
  {
    return $this->_polymorph . '_type';
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
      // Polymorphic relations
      if (Arr::get($this->_belongs_to[$column], 'polymorphic', FALSE) === TRUE)
      {
        $foreign_key = $this->polymorph_id();
        $foreign_type = $this->polymorph_type();

        $model = ORM::factory($this->_object[$foreign_type]);

        $col = $model->_object_name.'.'.$model->_primary_key;
        $val = $this->_object[$foreign_key];
      }
      else
      {
        $model = $this->_related($column);

        // Use this model's column and foreign model's primary key
        $col = $model->_object_name.'.'.$model->_primary_key;
        $val = $this->_object[$this->_belongs_to[$column]['foreign_key']];
      }


      // Make sure we don't run WHERE "AUTO_INCREMENT column" = NULL queries. This would
      // return the last inserted record instead of an empty result.
      // See: http://mysql.localhost.net.ar/doc/refman/5.1/en/server-session-variables.html#sysvar_sql_auto_is_null
      if ($val !== NULL)
      {
        $model->where($col, '=', $val)->find();
      }

      return $this->_related[$column] = $model;
    }
    else
    {
      return parent::get($column);
    }
  }


  public function set($column, $value)
  {
    if ( ! isset($this->_object_name))
    {
      // Object not yet constructed, so we're loading data from a database call cast
      $this->_cast_data[$column] = $value;
       
      return $this;
    }
     
    if (in_array($column, $this->_serialize_columns))
    {
      $value = $this->_serialize_value($value);
    }
 
    if (array_key_exists($column, $this->_object))
    {
      // Filter the data
      $value = $this->run_filter($column, $value);

      // See if the data really changed
      if ($value !== $this->_object[$column])
      {
        $this->_object[$column] = $value;

        // Data has changed
        $this->_changed[$column] = $column;

        // Object is no longer saved or valid
        $this->_saved = $this->_valid = FALSE;
      }
    }
    elseif (isset($this->_belongs_to[$column]))
    {
      // Update related object itself
      $this->_related[$column] = $value;

      // Polymorphic relations
      if (Arr::get($this->_belongs_to[$column], 'polymorphic', FALSE) === TRUE)
      {
        $foreign_key  = $object->_polymorph . '_id';
        $foreign_type = $object->_polymorph . '_type';

        $this->_object[$foreign_key]  = ($value instanceof ORM) ? $value->pk() : NULL;
        $this->_object[$foreign_type] = ($value instanceof ORM) ? $value->object_name() : NULL;
      }
      else
      {
        $foreign_key = $this->_belongs_to[$column]['foreign_key'];

        // Update the foreign key of this model
        $this->_object[$foreign_key] = ($value instanceof ORM) ? $value->pk() : NULL;
      }

      $this->_changed[$column] = $foreign_key;
    }
    else
    {
      throw new Kohana_Exception('The :property: property does not exist in the :class: class',
          array(':property:' => $column, ':class:' => get_class($this)));
    }
 
    return $this;
  }

}
