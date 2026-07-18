<?php

/*!
 * Perlite v1.6.1 (https://github.com/secure-77/Perlite)
 * Author: sec77 (https://secure77.de)
 * Licensed under MIT (https://github.com/secure-77/Perlite/blob/main/LICENSE)
 */

use Perlite\PerliteParsedown;
use Perlite\PerliteBasesRenderer;

require_once __DIR__ . '/vendor/autoload.php';
include('helper.php');

// check get params
if (isset($_GET['mdfile'])) {
	$requestFile = $_GET['mdfile'];

	if (is_string($requestFile)) {
		if (!empty($requestFile)) {
			global $avBaseFiles;
			menu($rootDir);
			if (in_array($requestFile, $avBaseFiles, true)) {
				renderBaseView($requestFile);
			} else {
				parseContent($requestFile);
			}
		}
	}
}

// parse content for about modal
if (isset($_GET['about'])) {

	if (is_string($_GET['about'])) {
		parseContent('/' . $about);
	}
}

// search request
if (isset($_GET['search'])) {

	$searchString = $_GET['search'];
	if (is_string($searchString)) {
		if (!empty($searchString)) {
			echo doSearch($rootDir, $searchString);
		}
	}
}


// parse content for home site
if (isset($_GET['home'])) {

	if (is_string($_GET['home'])) {
		parseContent('/' . $index);
	}
}


// parse the md to html
function parseContent($requestFile)
{

	global $path;
	global $uriPath;
	global $cleanFile;
	global $rootDir;
	global $startDir;
	global $lineBreaks;
	global $allowedFileLinkTypes;
	global $htmlSafeMode;
	global $absolutePath;
	global $niceLinks;


	// call menu again to refresh the array
	menu($rootDir);
	$path = '';

	// get and parse the content, return if no content is there
	$content = getContent($requestFile);
	if ($content === '') {
		return;
	}


	// Relative or absolute pathes
	if ($absolutePath) {
		$path = $startDir;
	} else {
		$path = $startDir . $path;
	}



	$Parsedown = new PerliteParsedown($path, $uriPath,$niceLinks, $allowedFileLinkTypes);
	$Parsedown->setSafeMode($htmlSafeMode);
	$Parsedown->setBreaksEnabled($lineBreaks);
	


	$wordCount = str_word_count($content);
	$charCount = strlen($content);
	$content = $Parsedown->text($content);


	// add some meta data
	$content = '
	<div style="display: none">
		<div class="mdTitleHide">' . $cleanFile . '</div>
		<div class="wordCount">' . $wordCount . '</div>
		<div class="charCount">' . $charCount . '</div>
	</div>' . $content;
	$cleanFile = '';

	echo $content;
	return;

}


// read content from file
function getContent($requestFile)
{
	global $avFiles;
	global $path;
	global $cleanFile;
	global $rootDir;
	$content = '';

	// check if file is in array
	if (in_array($requestFile, $avFiles, true)) {
		$cleanFile = $requestFile;
		$n = strrpos($requestFile, "/");
		$path = substr($requestFile, 0, $n);
		$content .= file_get_contents($rootDir . $requestFile . '.md', true);
	}

	return $content;
}

// render an Obsidian Bases (.base) file as a tabbed set of table views
function renderBaseView($requestFile)
{
	global $rootDir;

	$absolutePath = $rootDir . $requestFile . '.base';
	if (!is_file($absolutePath)) {
		return;
	}

	$baseConfig = PerliteBasesRenderer::loadBaseFile($absolutePath);
	$allRows = PerliteBasesRenderer::collectRows($rootDir);

	$content = '
	<div style="display: none">
		<div class="mdTitleHide">' . htmlspecialchars($requestFile) . '</div>
		<div class="wordCount">0</div>
		<div class="charCount">0</div>
	</div>';

	$content .= '<div class="bases-view">';
	$content .= '<div class="bases-tabs">';
	foreach ($baseConfig['views'] as $i => $view) {
		$active = $i === 0 ? ' is-active' : '';
		$viewName = $view['name'] ?? ('View ' . ($i + 1));
		$content .= '<div class="bases-tab' . $active . '" onclick="switchBaseView(this, ' . $i . ');">' . htmlspecialchars($viewName) . '</div>';
	}
	$content .= '</div>';

	foreach ($baseConfig['views'] as $i => $view) {
		$viewData = PerliteBasesRenderer::buildView($baseConfig, $view, $allRows);
		$display = $i === 0 ? '' : ' style="display:none;"';
		$content .= '<div class="bases-table-wrap" data-bases-panel="' . $i . '"' . $display . '>';
		$content .= renderBaseTable($viewData);
		$content .= '</div>';
	}
	$content .= '</div>';

	echo $content;
}

function renderBaseTable($viewData)
{
	$rowHeightClass = 'bases-row-' . preg_replace('/[^a-z]/', '', strtolower($viewData['rowHeight'] ?: 'default'));
	$columnCount = max(1, count($viewData['columns']));

	$html = '<table class="bases-table ' . $rowHeightClass . '"><thead><tr>';
	foreach ($viewData['columns'] as $col) {
		$style = $col['width'] ? ' style="width:' . (int) $col['width'] . 'px;"' : '';
		$html .= '<th' . $style . '>' . htmlspecialchars($col['displayName']) . '</th>';
	}
	$html .= '</tr></thead><tbody>';

	foreach ($viewData['rows'] as $row) {
		$html .= '<tr>';
		foreach ($viewData['columns'] as $col) {
			$value = $col['isFormula'] ? ($row['formulas'][$col['name']] ?? '') : ($row['properties'][$col['name']] ?? '');
			if (is_array($value) || is_bool($value)) {
				$value = '';
			}
			$html .= '<td>' . nl2br(htmlspecialchars((string) $value)) . '</td>';
		}
		$html .= '</tr>';
	}

	if (empty($viewData['rows'])) {
		$html .= '<tr><td colspan="' . $columnCount . '" class="bases-empty">No results found.</td></tr>';
	}

	$html .= '</tbody></table>';
	return $html;
}

?>