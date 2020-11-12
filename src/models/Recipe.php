<?php
/**
 * Recipe plugin for Craft CMS 3.x
 *
 * A comprehensive recipe FieldType for Craft CMS that includes metric/imperial
 * conversion, portion calculation, and JSON-LD microdata support
 *
 * @link      https://nystudio107.com
 * @copyright Copyright (c) 2017 nystudio107
 */

namespace nystudio107\recipe\models;

use nystudio107\recipe\helpers\Json;

use Craft;
use craft\base\Model;
use craft\helpers\Template;

/**
 * @author    nystudio107
 * @package   Recipe
 * @since     1.0.0
 */
class Recipe extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $skill = 'intermediate';

    /**
     * @var int
     */
    public $serves = 1;

    /**
     * @var array
     */
    public $ingredients = [];

    /**
     * @var array
     */
    public $directions = [];

    /**
     * @var int
     */
    public $imageId = 0;

    /**
     * @var int
     */
    public $prepTime;

    /**
     * @var int
     */
    public $cookTime;

    /**
     * @var int
     */
    public $totalTime;

    /**
     * @var string
     */
    public $servingSize;

    /**
     * @var int
     */
    public $calories;

    /**
     * @var int
     */
    public $carbohydrateContent;

    /**
     * @var int
     */
    public $cholesterolContent;

    /**
     * @var int
     */
    public $fatContent;

    /**
     * @var int
     */
    public $fiberContent;

    /**
     * @var int
     */
    public $proteinContent;

    /**
     * @var int
     */
    public $saturatedFatContent;

    /**
     * @var int
     */
    public $sodiumContent;

    /**
     * @var int
     */
    public $sugarContent;

    /**
     * @var int
     */
    public $transFatContent;

    /**
     * @var int
     */
    public $unsaturatedFatContent;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['name', 'string'],
            ['name', 'default', 'value' => ''],
            ['description', 'string'],
            ['skill', 'string'],
            ['serves', 'integer'],
            ['imageId', 'integer'],
            ['prepTime', 'integer'],
            ['cookTime', 'integer'],
            ['totalTime', 'integer'],
            ['servingSize', 'string'],
            ['calories', 'string'],
            ['carbohydrateContent', 'string'],
            ['cholesterolContent', 'string'],
            ['fatContent', 'string'],
            ['fiberContent', 'string'],
            ['proteinContent', 'string'],
            ['saturatedFatContent', 'string'],
            ['sodiumContent', 'string'],
            ['sugarContent', 'string'],
            ['transFatContent', 'string'],
            ['unsaturatedFatContent', 'string'],
        ];
    }

    /**
     * Render the JSON-LD Structured Data for this recipe
     *
     * @param bool $raw
     *
     * @return string|\Twig_Markup
     */
    public function renderRecipeJSONLD($entry, $tags, $categories)
    {
        $author = $entry->articleAuthor[0] ?? null;;
        $authorTitle = $author['title'] ?? null;

        $image = $entry->image[0] ?? null;
        $imageUrl = !empty($image) ? $image->getUrl([
            'mode' => 'crop',
            'width' => 1200,
            'height' => 630,
            'quality' => 65,
            'position' => 'center-center',
            'format' => 'jpg'
        ]) : null;

        $videoUrl = $entry->videoLink ?? null;
        $videoId = $this->getVideoId($videoUrl) ?? null;

        $recipeJSONLD = [
            "context" => "http://schema.org",
            "type" => "Recipe",
            "name" => $this->name,
            "image" => $imageUrl,
            "description" => $this->description,
            "recipeYield" => $this->serves,
            "recipeIngredient" => $this->getIngredients(false),
            "recipeCategory" => $categories,
            "recipeInstructions" => array_map(
                function ($direction) {
                    return [
                        '@type' => 'HowToStep',
                        'text' => $direction
                    ];
                },
                $this->getDirections(false)
            ),
            'author' => $authorTitle ? [
                '@context' => 'http://schema.org',
                '@type' => 'Person',
                'name' => $authorTitle
            ] : null,
            'keywords' => implode($tags, ', '),
            'video' => $videoId ? [
                '@context' => 'http://schema.org',
                '@type' => 'VideoObject',
                'name' => $this->name,
                'description' => $this->description,
                'thumbnailURL' => 'http://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg',
                'embedUrl' => 'https://www.youtube.com/embed/' . $videoId . '?rel=0',
                'uploadDate' => $entry->postDate->format('Y-m-d H:i:s')
            ] : null
        ];
        $recipeJSONLD = array_filter($recipeJSONLD);

        $nutrition = $this->getNutritions();
        $recipeJSONLD['nutrition'] = $nutrition;
        if (count($recipeJSONLD['nutrition']) == 1) {
            unset($recipeJSONLD['nutrition']);
        }

        if ($this->prepTime) {
            $recipeJSONLD['prepTime'] = "PT".$this->prepTime."M";
        }
        if ($this->cookTime) {
            $recipeJSONLD['cookTime'] = "PT".$this->cookTime."M";
        }
        if ($this->totalTime) {
            $recipeJSONLD['totalTime'] = "PT".$this->totalTime."M";
        }

        return $this->renderJsonLd($recipeJSONLD, true);
    }

    /**
     * Get the URL to the recipe's image
     *
     * @return null|string
     */
    public function getImageUrl()
    {
        $result = "";
        if (isset($this->imageId) && $this->imageId) {
            $image = Craft::$app->getAssets()->getAssetById($this->imageId[0]);
            if ($image) {
                $result = $image->url;
            }
        }

        return $result;
    }

    /**
     * Get all of the ingredients for this recipe
     *
     * @param bool   $raw
     *
     * @return array
     */
    public function getIngredients($raw = true)
    {
        $result = [];
        foreach ($this->ingredients as $row) {
            $ingredient = "";
            if ($row['ingredient']) {
                $ingredient .= " ".$row['ingredient'];
            }
            if ($raw) {
                $ingredient = Template::raw($ingredient);
            }
            array_push($result, $ingredient);
        }

        return $result;
    }

    /**
     * Get all of the directions for this recipe
     *
     * @param bool $raw
     *
     * @return array
     */
    public function getDirections($raw = true)
    {
        $result = [];
        foreach ($this->directions as $row) {
            $direction = $row['direction'];
            if ($raw) {
                $direction = Template::raw($direction);
            }
            array_push($result, $direction);
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getNutritions()
    {
        return array_filter([
            'calories' => $this->calories,
            'carbohydrateContent' => $this->carbohydrateContent,
            'cholesterolContent' => $this->cholesterolContent,
            'fatContent' => $this->fatContent,
            'fiberContent' => $this->fiberContent,
            'proteinContent' => $this->proteinContent,
            'saturatedFatContent' => $this->saturatedFatContent,
            'sodiumContent' => $this->sodiumContent,
            'sugarContent' => $this->sugarContent,
            'transFatContent' => $this->transFatContent,
            'unsaturatedFatContent' => $this->unsaturatedFatContent,
        ]);
    }

    // Private Methods
    // =========================================================================

    /**
     * Renders a JSON-LD representation of the schema
     *
     * @param      $json
     * @param bool $raw
     *
     * @return string|\Twig_Markup
     */
    private function renderJsonLd($json, $raw = true)
    {
        $linebreak = "";

        // If `devMode` is enabled, make the JSON-LD human-readable
        if (Craft::$app->getConfig()->getGeneral()->devMode) {
            $linebreak = PHP_EOL;
        }

        // Render the resulting JSON-LD
        $result = '<script type="application/ld+json">'
            .$linebreak
            .Json::encode($json)
            .$linebreak
            .'</script>';

        if ($raw === true) {
            $result = Template::raw($result);
        }

        return $result;
    }

    private function getVideoId($videoUrl)
    {
        $videoParts = [];
        preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $videoUrl, $videoParts);

        if (empty($videoParts)) {
            return false;
        }

        return $videoParts[1] ?? null;
    }
}
