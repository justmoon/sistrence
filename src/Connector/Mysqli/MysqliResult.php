<?php
namespace Sistrence\Connector\Mysqli;

class MysqliResult
{
  protected $result;

  public function __construct(mysqli_result $result)
  {
    $this->result = $result;
  }

  public function fetchAll()
  {
    $rows = array();
    while ($row = $this->result->fetch_assoc()) {
      $rows[] = $row;
    }
    return $rows;
  }

  public function fetchAllSingle()
  {
    $values = array();
    while (list($val) = $this->result->fetch_row()) {
      $values[] = $val;
    }
    return $values;
  }
}
