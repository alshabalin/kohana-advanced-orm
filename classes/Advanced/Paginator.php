<?php

class Advanced_Paginator {

  protected $_count_per_page = 10;
  protected $_count          = 0;
  protected $_page_count     = 0;
  protected $_current_page   = 1;
  protected $_current_offset = 0;
  protected $_next_page      = NULL;
  protected $_prev_page      = NULL;
  protected $_page_get_param = 'page';


  public function __construct($count = 0, $count_per_page = NULL, $page_get_param = NULL)
  {
    $this->_count = $count;

    if ($count_per_page !== NULL)
    {
      $this->_count_per_page = max(1, $count_per_page);
    }

    if ($page_get_param !== NULL)
    {
      $this->_page_get_param = $page_get_param;
    }

    $this->_page_count     = ceil($this->_count / $this->_count_per_page);
    $this->_current_page   = min(max(1, (int)Arr::get($_GET, $this->_page_get_param, 1)), $this->_page_count);
    $this->_current_offset = min(max(0, ($this->_current_page - 1) * $this->_count_per_page), $this->_count);
    $this->_next_page      = $this->_current_page + 1;
    $this->_prev_page      = $this->_current_page - 1;

    if ($this->_next_page > $this->_page_count)
    {
      $this->_next_page = NULL;
    }

    if ($this->_prev_page < 1)
    {
      $this->_prev_page = NULL;
    }

    static::$current = $this;
  }

  public static $current = NULL;

  public static function current()
  {
    return static::$current;
  }

  public static function render_links($view = 'paginator')
  {
    return View::factory($view, static::$current->as_array())->render();
  }

  public function as_array()
  {
    return [
      'count_per_page' => $this->_count_per_page,
      'count'          => $this->_count,
      'page_count'     => $this->_page_count,
      'current_page'   => $this->_current_page,
      'current_offset' => $this->_current_offset,
      'next_page'      => $this->_next_page,
      'prev_page'      => $this->_prev_page,
      'page_get_param' => $this->_page_get_param,
    ];
  }


  public function count_per_page()
  {
    return $this->_count_per_page;
  }

  public function count()
  {
    return $this->_count;
  }

  public function page_count()
  {
    return $this->_page_count;
  }

  public function current_page()
  {
    return $this->_current_page;
  }

  public function current_offset()
  {
    return $this->_current_offset;
  }

  public function next_page()
  {
    return $this->_next_page;
  }

  public function prev_page()
  {
    return $this->_prev_page;
  }




}
