# yii2-doc-bookmarks

- [Download](#download)
- [Installation](#installation)
- [Usage](#usage)

## Download

Your can download the latest bookmarks on the following site: https://data.grinvik.com.

## Installation

``` sh
composer require w3lifer/yii2-doc-bookmarks
```

## Usage

``` php
<?php

namespace app\commands;

use w3lifer\yii2\DocBookmarks;
use Yii;
use yii\console\Controller;

class DocBookmarksController extends Controller
{
    /**
     * ``` sh
     * php yii doc-bookmarks
     * ```
     */
    public function actionIndex()
    {
        $docBookmarks = new DocBookmarks();

        file_put_contents(
            Yii::getAlias('@runtime') . '/doc-bookmarks-as-array.php',
            '<?php' . "\n\n" .
                var_export($docBookmarks->getAsArray(), true) . ';'
        );

        file_put_contents(
            Yii::getAlias('@runtime') .
                '/doc-bookmarks-as-netscape-bookmarks.html',
            $docBookmarks->getAsNetscapeBookmarks()
        );

        echo 'Done!' . "\n";
    }
}
```
