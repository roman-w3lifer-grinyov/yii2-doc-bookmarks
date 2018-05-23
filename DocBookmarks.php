<?php

namespace w3lifer\yii2;

use w3lifer\netscapeBookmarks\NetscapeBookmarks;

/**
 * @see https://github.com/yiisoft/yii2/tree/master/docs/guide
 */
class DocBookmarks
{
    const BASE_LINK_GITHUB_COM =
        'https://raw.githubusercontent.com/yiisoft/yii2/master/docs/guide';

    const BASE_LINK_YIIFRAMEWORK_COM =
        'https://www.yiiframework.com/doc/guide/2.0/en';

    /**
     * @var array
     */
    private $tableOfContents = [];

    /**
     * @var array
     */
    private $bookmarks = [];

    /**
     * @return string
     */
    public function getAsNetscapeBookmarks()
    {
        if (!$this->tableOfContents) {
            $this->tableOfContents = $this->getTableOfContents();
        }
        if (!$this->bookmarks) {
            $this->makeBookmarks($this->tableOfContents);
        }
        return new NetscapeBookmarks($this->bookmarks);
    }

    /**
     * @return array
     */
    public function getAsArray()
    {
        if (!$this->tableOfContents) {
            $this->tableOfContents = $this->getTableOfContents();
        }
        if (!$this->bookmarks) {
            $this->makeBookmarks($this->tableOfContents);
        }
        return $this->bookmarks;
    }

    /**
     * Example of the returned array:
     * ``` php
     * [
     *     // ...
     *     'Working with Databases' => [
     *         'Active Record' => 'db-active-record.md',
     *         'Data Access Objects' => 'db-dao.md',
     *         'ElasticSearch' => 'https://www.yiiframework.com/extension/yiisoft/yii2-elasticsearch/doc/guide',
     *         // ...
     *     ],
     *     // ...
     * ]
     * ```
     * @return array
     */
    private function getTableOfContents()
    {
        $tableOfContents =
            file_get_contents(
                self::BASE_LINK_GITHUB_COM . '/README.md'
            );

        // Section names

        preg_match_all('=\n\n\n(.+?)\n\-+\n\n=i', $tableOfContents, $matches);
        $sectionNames = $matches[1];

        // Section contents

        $sectionContents =
            preg_split(
                '=\n\n\n(.+?)\n\-+\n\n=i',
                $tableOfContents
            );
        array_shift($sectionContents);

        // Table of contents

        $tableOfContents = [];

        foreach ($sectionNames as $index => $sectionName) {
            preg_match_all(
                '=\* \[(.+?)\]\((.+?)\)=',
                $sectionContents[$index],
                $matches
            );
            $tableOfContents[$sectionName] =
                array_combine($matches[1], $matches[2]);
        }

        return $tableOfContents;
    }

    private function makeBookmarks($tableOfContents)
    {
        $this->bookmarks['Guide'] = self::BASE_LINK_YIIFRAMEWORK_COM;
        foreach ($tableOfContents as $sectionName => $articles) {
            $this->bookmarks[$sectionName] = [];
            foreach ($articles as $articleName => $filename) {
                if (preg_match('=^https?\://=', $filename)) {
                    $this->bookmarks[$sectionName][$articleName] = $filename;
                    continue;
                }
                $fileContent =
                    file_get_contents(
                        self::BASE_LINK_GITHUB_COM . '/' . $filename
                    );
                $this->processAnchors(
                    $sectionName,
                    $articleName,
                    $filename,
                    $fileContent
                );
            }
        }
        $this->addSerialNumbersToSectionAndArticleNames();
    }

    private function processH1Anchor(
        $sectionName,
        $articleName,
        $filename,
        $fileContent
    ) {
        preg_match_all(
            '=^(?:(.+?)\n\=+|# (.+?)\n)=s',
            $fileContent,
            $h1
        );
        $h1 = !empty($h1[1][0]) ? $h1[1][0] : $h1[2][0];
        $this->bookmarks[$sectionName][$articleName] = [
            '0. ' . $h1 => self::BASE_LINK_YIIFRAMEWORK_COM . '/' . $filename,
            '1. ' . $h1 =>
                self::BASE_LINK_YIIFRAMEWORK_COM . '/' .
                    $filename . self::getAnchor($h1),
        ];
    }

    private function processAnchors(
        $sectionName,
        $articleName,
        $filename,
        $fileContent
    ) {
        $filename = preg_replace('=\.md$=', '', $filename);
        $this->processH1Anchor(
            $sectionName,
            $articleName,
            $filename,
            $fileContent
        );
        preg_match_all(
            '=(?:\n(.+?)\n\-{4,}|\n(#{2,6} .+?)\n)=',
            $fileContent,
            $headers
        );
        foreach ($headers[1] as $index => $header) {
            $header =
                $header !== ''
                    ? '## ' . $header
                    : $headers[2][$index];
            $header = self::purifyHeader($header);
            $header = trim($header);
            $headerWithSerialNumber =
                self::getAnchorWithSerialNumber(
                    $sectionName,
                    $articleName,
                    $header
                );
            $this->bookmarks
                [$sectionName]
                    [$articleName]
                        [$headerWithSerialNumber] =
                            self::BASE_LINK_YIIFRAMEWORK_COM . '/' .
                                $filename .
                                    self::getAnchor($header);
        }
    }

    /**
     * @var array
     */
    private static $filtersForHeaders = [
        '=<(?:span|a) .*?>.*?(?:</(span|a)>)?=' => '',
        '=\[\[.+?\|(.+?)\]\]=' => '$1', // [[yii\base\Application::id|id]]
    ];

    /**
     * @param string $header
     * @return string
     */
    private static function purifyHeader($header)
    {
        foreach (self::$filtersForHeaders as $pattern => $replacement) {
            $header = preg_replace($pattern, $replacement, $header);
        }
        return $header;
    }

    /**
     * @var array
     */
    private static $serialNumberStorage = [];

    /**
     * @var string
     */
    private static $lastSerialNumberHashString = '';

    /**
     * @param string $sectionName
     * @param string $articleName
     * @param string $anchor
     * @return string
     */
    private static function getAnchorWithSerialNumber(
        $sectionName,
        $articleName,
        $anchor
    ) {
        if (!isset(self::$serialNumberStorage[$sectionName])) {
            self::$serialNumberStorage[$sectionName] = [];
            if (
                !isset(self::$serialNumberStorage[$sectionName][$articleName])
            ) {
                self::$serialNumberStorage[$sectionName][$articleName] = [];
            }
        }

        preg_match('=^#+=', $anchor, $matches);
        $hashString = $matches[0];

        $anchor = preg_replace('=^#+ =', '', $anchor);

        if (!isset(
            self::$serialNumberStorage
                [$sectionName][$articleName][$hashString]
        )) {
            if ($hashString === '##') {
                self::$serialNumberStorage
                    [$sectionName][$articleName][$hashString] = 1;
            } else {
                self::$serialNumberStorage
                    [$sectionName][$articleName][$hashString] = 0;
            }
        }

        if (strlen($hashString) < strlen(self::$lastSerialNumberHashString)) {
            self::$serialNumberStorage
                [$sectionName]
                    [$articleName]
                        [self::$lastSerialNumberHashString] = 0;
        }

        self::$serialNumberStorage[$sectionName][$articleName][$hashString]++;

        $serialNumberOfAnchor = substr_count($hashString, '#');

        $serialNumber = '';
        for ($i = 2; $i <= $serialNumberOfAnchor; $i++) {
            $growingHash = str_repeat('#', $i);
            // For the case when exists leap between headers
            // For example, #### going after ##
            if (!isset(
                self::$serialNumberStorage
                    [$sectionName][$articleName][$growingHash]
            )) {
                self::$serialNumberStorage
                    [$sectionName][$articleName][$growingHash] = 1;
            }
            $serialNumber .=
                self::$serialNumberStorage
                    [$sectionName]
                        [$articleName]
                            [$growingHash] . '.';
        }

        self::$lastSerialNumberHashString = $hashString;

        return $serialNumber . ' ' . $anchor;
    }

    private static function getAnchor($anchor)
    {
        $anchor = strtolower($anchor);
        $anchor = preg_replace('=[^ \-0-9a-z]=', '', $anchor);
        $anchor = trim($anchor);
        $anchor = str_replace([' '], '-', $anchor);
        return '#' . $anchor;
    }

    private function addSerialNumbersToSectionAndArticleNames()
    {
        $i = 0;
        $bookmarks = [];
        foreach ($this->bookmarks as $sectionName => $articles) {
            if (is_array($articles)) {
                $articlesWithSerialNumbers = [];
                $j = 1;
                foreach ($articles as $articleName => $anchors) {
                    $articlesWithSerialNumbers[$j . '. ' . $articleName] =
                        $anchors;
                    $j++;
                }
                $bookmarks[$i . '. ' . $sectionName] =
                    $articlesWithSerialNumbers;
            } else {
                $bookmarks[$i . '. ' . $sectionName] = $articles;
            }
            $i++;
        }
        $this->bookmarks = $bookmarks;
    }
}
