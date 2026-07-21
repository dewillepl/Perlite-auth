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


// lightweight vault change check, polled by the frontend to auto-refresh
// the file tree without a full page reload
if (isset($_GET['vaultState'])) {
	echo md5(getVaultStateSignature($rootDir));
}

// refreshed file tree markup, fetched only after vaultState reports a change
if (isset($_GET['menu'])) {
	echo menu($rootDir);
}

// mtime of a single note, used to silently refresh the currently open note
// if it gets edited on disk while the user is viewing it
if (isset($_GET['fileState'])) {
	$requestFile = $_GET['fileState'];

	if (is_string($requestFile) && !empty($requestFile)) {
		menu($rootDir);
		if (in_array($requestFile, $avFiles, true)) {
			$fp = $rootDir . $requestFile . '.md';
			if (is_file($fp)) {
				echo filemtime($fp);
			}
		}
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
			$html .= '<td>' . nl2br(renderBaseCellValue((string) $value, $row['file']['folder'])) . '</td>';
		}
		$html .= '</tr>';
	}

	if (empty($viewData['rows'])) {
		$html .= '<tr><td colspan="' . $columnCount . '" class="bases-empty">No results found.</td></tr>';
	}

	$html .= '</tbody></table>';
	return $html;
}

// render a Bases cell's raw text, turning [[wikilinks]] and [markdown](links) into clickable links
function renderBaseCellValue($value, $rowFolder)
{
	if ($value === '') {
		return '';
	}

	$pattern = '/\[\[([^\]|]+)(?:\|([^\]]+))?\]\]|\[([^\]]+)\]\((https?:\/\/[^)\s]+|mailto:[^)\s]+)\)/i';

	if (!preg_match_all($pattern, $value, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}

	$html = '';
	$cursor = 0;

	foreach ($matches as $m) {
		$full = $m[0][0];
		$offset = $m[0][1];

		$html .= htmlspecialchars(substr($value, $cursor, $offset - $cursor), ENT_QUOTES, 'UTF-8');

		if (isset($m[1]) && $m[1][0] !== '') {
			// wikilink: [[target|label]]
			$target = trim($m[1][0]);
			$label = (isset($m[2]) && $m[2][0] !== '') ? trim($m[2][0]) : $target;

			$anchor = '';
			$hashPos = strpos($target, '#');
			if ($hashPos !== false) {
				$anchor = substr($target, $hashPos + 1);
				$target = substr($target, 0, $hashPos);
			}
			if (strcasecmp(substr($target, -3), '.md') === 0) {
				$target = substr($target, 0, -3);
			}

			$path = ($rowFolder !== '' ? $rowFolder . '/' : '') . $target;
			$jsPath = rawurlencode('/' . $path);
			$jsAnchor = $anchor !== '' ? "'#" . rawurlencode($anchor) . "'" : "''";

			$html .= '<a href="#" class="internal-link" onclick="getContent(\'' . $jsPath . '\', false, false, ' . $jsAnchor . '); return false;">'
				. htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
		} else {
			// markdown link: [label](url)
			$label = $m[3][0];
			$url = $m[4][0];
			$html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="external-link" target="_blank" rel="noopener noreferrer">'
				. htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
		}

		$cursor = $offset + strlen($full);
	}

	$html .= htmlspecialchars(substr($value, $cursor), ENT_QUOTES, 'UTF-8');

	return $html;
}

?>