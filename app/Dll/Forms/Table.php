<?php

// Dll/Forms/Table.php
namespace Dll\Forms;

class Table
{
	protected $head;
	protected $data;
	protected $options;

	public function __construct(array $head, array $data, array $options = [])
	{
		$this->head = $head;
		$this->data = $data;
		$this->options = $options;
	}

	public function render()
	{
		$html = '<table';

		if (isset($this->options['table'])) {
			foreach ($this->options['table'] as $key => $value) {
				$html .= ' ' . $key . '="' . $value . '"';
			}
		}

		$html .= '>';

		if (!empty($this->head)) {
			$html .= '<thead><tr>';
			foreach ($this->head as $index => $label) {
				$html .= '<th';
				if (isset($this->options['thead'][$index])) {
					foreach ($this->options['thead'][$index] as $key => $value) {
						$html .= ' ' . $key . '="' . $value . '"';
					}
				}
				$html .= '>' . $label . '</th>';
			}
			$html .= '</tr></thead>';
		}

		if (!empty($this->data)) {
			$html .= '<tbody>';
			foreach ($this->data as $row) {
				$html .= '<tr>';
				foreach ($row as $cell) {
					$html .= '<td>' . $cell . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody>';
		}

		$html .= '</table>';

		return $html;
	}
}
