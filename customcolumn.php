<?php
/**
 * COPS (Calibre OPDS PHP Server) class file
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Sébastien Lucas <sebastien@slucas.fr>
 */

require_once('base.php');

/**
 * A CustomColumn with an value
 */
class CustomColumn extends Base {
    /* @var string|integer the ID of the value */
    public $valueID;
    /* @var string the (string) representation of the value */
    public $name;
    /* @var CustomColumnType the custom column that contains the value */
    public $customColumnType;

    /**
     * CustomColumn constructor.
     *
     * @param integer $pid id of the chosen value
     * @param string $pname string representation of the value
     * @param CustomColumnType $pcustomColumnType the CustomColumn this value lives in
     */
    public function __construct($pid, $pname, $pcustomColumnType) {
        $this->valueID = $pid;
        $this->name = $pname;
        $this->customColumnType = $pcustomColumnType;
    }

    /**
     * Get the URI to show all books with this value
     *
     * @return string
     */
    public function getUri() {
        return $this->customColumnType->getUri($this->valueID);
    }

    /**
     * Get the EntryID to show all books with this value
     *
     * @return string
     */
    public function getEntryId() {
        return $this->customColumnType->getEntryId($this->valueID);
    }

    /**
     * Get the query to find all books with this value
     *
     * @return string
     */
    public function getQuery() {
        return $this->customColumnType->getQuery($this->valueID);
    }


    /**
     * Craete an CustomColumn by CustomColumnID and ValueID
     *
     * @param integer $customId the id of the customColumn
     * @param integer $id the id of the chosen value
     * @return CustomColumn|null
     */
    public static function createCustom($customId, $id) {
        $columnType = CustomColumnType::createByCustomID($customId);

        return $columnType->getCustom($id);
    }
}

/**
 * A single calibre custom column
 */
abstract class CustomColumnType extends Base {
    const ALL_CUSTOMS_ID       = "cops:custom";

    const CUSTOM_TYPE_TEXT      = "text";        // type 1 + 2
    const CUSTOM_TYPE_COMMENTS  = "comments";    // type 3
    const CUSTOM_TYPE_SERIES    = "series";      // type 4
    const CUSTOM_TYPE_ENUM      = "enumeration"; // type 5
    const CUSTOM_TYPE_DATE      = "datetime";    // type 6
    const CUSTOM_TYPE_FLOAT     = "float";       // type 7
    const CUSTOM_TYPE_INT       = "int";         // type 8
    const CUSTOM_TYPE_RATING    = "rating";      // type 9
    const CUSTOM_TYPE_BOOL      = "bool";        // type 10
    const CUSTOM_TYPE_COMPOSITE = "composite";   // type 11 + 12

    /** @var integer the id of this column */
    public $customId;
    /** @var string name/title of this column */
    public $columnTitle;
    /** @var string the datatype of this column (one of the CUSTOM_TYPE_* constant values) */
    public $datatype;
    /** @var Entry[] */
    private $customValues = NULL;

    protected function __construct($pcustomId, $pdatatype) {
        $this->columnTitle = self::getTitleByCustomID($pcustomId);
        $this->customId = $pcustomId;
        $this->datatype = $pdatatype;
        $this->customValues = $this->getAllCustomValuesFromDatabase();
    }

    /**
     * The URI to show all book swith a specific value in this column
     *
     * @param string|integer $id the id of the value to show
     * @return string
     */
    public function getUri($id) {
        return "?page=".parent::PAGE_CUSTOM_DETAIL."&custom={$this->customId}&id={$id}";
    }

    /**
     * The URI to show all the values of this column
     *
     * @return string
     */
    public function getUriAllCustoms() {
        return "?page=" . parent::PAGE_ALL_CUSTOMS . "&custom={$this->customId}";
    }

    /**
     * The EntryID to show all book swith a specific value in this column
     *
     * @param string|integer $id the id of the value to show
     * @return string
     */
    public function getEntryId($id) {
        return self::ALL_CUSTOMS_ID.":".$this->customId.":".$id;
    }

    /**
     * The EntryID to show all the values of this column
     *
     * @return string
     */
    public function getAllCustomsId() {
        return self::ALL_CUSTOMS_ID . ":" . $this->customId;
    }

    /**
     * The title of this column
     * 
     * @return string
     */
    public function getTitle() {
        return $this->columnTitle;
    }

    /**
     * The description of this column as it is definied in the database
     *
     * @return string|null
     */
    public function getDatabaseDescription() {
        $result = parent::getDb()->prepare('select display from custom_columns where id = ?');
        $result->execute(array($this->customId));
        if ($post = $result->fetchObject()) {
            $json = json_decode($post->display);
            return (isset($json->description) && !empty($json->description)) ? $json->description : NULL;
        }
        return NULL;
    }

    /**
     * Get the Entry for this column
     * This is used in the initializeContent method to display e.g. the index page
     *
     * @return Entry
     */
    public function getCount() {
        $ptitle = $this->getTitle();
        $pid = $this->getAllCustomsId();
        $pcontent = $this->getDescription();
        $pcontentType = $this->datatype;
        $plinkArray = array(new LinkNavigation($this->getUriAllCustoms()));
        $pclass = "";
        $pcount = $this->getDistinctValueCount();

        return new Entry($ptitle, $pid, $pcontent, $pcontentType, $plinkArray, $pclass, $pcount);
    }

    /**
     * Get the amount of distinct values for this column
     *
     * @return int
     */
    protected function getDistinctValueCount()
    {
        return count($this->customValues);
    }

    /**
     * Get the datatype of a CustomColumn by its customID
     *
     * @param integer $customId
     * @return string|null
     */
    private static function getDatatypeByCustomID($customId) {
        $result = parent::getDb()->prepare('select datatype from custom_columns where id = ?');
        $result->execute(array($customId));
        if ($post = $result->fetchObject()) {
            return $post->datatype;
        }
        return NULL;
    }

    /**
     * Create a CustomColumnType by CustomID
     *
     * @param integer $customId the id of the custom column
     * @return CustomColumnType|null
     * @throws Exception If the $customId is not found or the datatype is unknown
     */
    public static function createByCustomID($customId) {
        $datatype = self::getDatatypeByCustomID($customId);

        switch ($datatype){
            case self::CUSTOM_TYPE_TEXT:
                return new CustomColumnTypeText($customId);
            case self::CUSTOM_TYPE_SERIES:
                return new CustomColumnTypeSeries($customId);
            case self::CUSTOM_TYPE_ENUM:
                return new CustomColumnTypeEnumeration($customId);
            case self::CUSTOM_TYPE_COMMENTS:
                return NULL; // Not supported - Doesn't really make sense
            case self::CUSTOM_TYPE_DATE:
                return new CustomColumnTypeDate($customId);
            case self::CUSTOM_TYPE_FLOAT:
                return new CustomColumnTypeFloat($customId);
            case self::CUSTOM_TYPE_INT:
                return new CustomColumnTypeInteger($customId);
            case self::CUSTOM_TYPE_RATING:
                return new CustomColumnTypeRating($customId);
            case self::CUSTOM_TYPE_BOOL:
                return new CustomColumnTypeBool($customId);
            case self::CUSTOM_TYPE_COMPOSITE:
                return NULL; //TODO Currently not supported
            default:
                throw new Exception("Unkown column type: " . $datatype);
        }
    }

    /**
     * Create a CustomColumnType by its lookup name
     *
     * @param string $lookup the lookup-name of the custom column
     * @return CustomColumnType|null
     */
    public static function createByLookup($lookup) {
        $result = parent::getDb ()->prepare('select id from custom_columns where label = ?');
        $result->execute (array ($lookup));
        if ($post = $result->fetchObject ()) {
            return self::createByCustomID($post->id);
        }
        return NULL;
    }

    /**
     * Return an entry array for all possible (in the DB used) values of this column
     * These are the values used in the getUriAllCustoms() page
     *
     * @return Entry[]
     */
    public function getAllCustomValues() {
        return $this->customValues;
    }

    /**
     * Get the title of a CustomColumn by its customID
     *
     * @param integer $customId
     * @return string|null
     */
    protected static function getTitleByCustomID($customId) {
        $result = parent::getDb ()->prepare('select name from custom_columns where id = ?');
        $result->execute (array ($customId));
        if ($post = $result->fetchObject ()) {
            return $post->name;
        }
        return NULL;
    }

    /**
     * Get the name of the sqlite table for this column
     *
     * @return string|null
     */
    abstract public function getTableName ();

    /**
     * Get the name of the linking sqlite table for this column
     * (or NULL if there is no linktable)
     *
     * @return string|null
     */
    abstract public function getTableLinkName ();

    /**
     * Get the name of the linking column in the linktable
     *
     * @return string|null
     */
    abstract public function getTableLinkColumn();

    /**
     * Get the query to find all books with a specific value of this column
     * the returning array has two values:
     *  - first the query (string)
     *  - second an array of all PreparedStatement parameters
     *
     * @param string|integer $id the id of the searched value
     * @return array
     */
    abstract public function getQuery($id);

    /**
     * Get a CustomColumn for a specified (by ID) value
     *
     * @param string|integer $id the id of the searched value
     * @return CustomColumn
     */
    abstract public function getCustom($id);

    /**
     * Return an entry array for all possible (in the DB used) values of this column by querying the database
     *
     * @return Entry[]
     */
    abstract protected function getAllCustomValuesFromDatabase();

    /**
     * The description used in the index page
     *
     * @return string
     */
    abstract public function getDescription();

    /**
     * Find the value of this column for a specific book
     *
     * @param Book $book
     * @return CustomColumn
     */
    public abstract function getCustomByBook($book);
}

class CustomColumnTypeText extends CustomColumnType
{
    protected function __construct($pcustomId) {
        parent::__construct($pcustomId, self::CUSTOM_TYPE_TEXT);
    }

    public function getTableName() {
        return "custom_column_{$this->customId}";
    }

    public function getTableLinkName() {
        return "books_custom_column_{$this->customId}_link";
    }

    public function getTableLinkColumn() {
        return "value";
    }

    public function getQuery($id) {
        $query = str_format(Book::SQL_BOOKS_BY_CUSTOM, "{0}", "{1}", $this->getTableLinkName(), $this->getTableLinkColumn());
        return array($query, array($id));
    }

    public function getCustom($id) {
        $result = parent::getDb()->prepare(str_format("select id, value as name from {0} where id = ?", $this->getTableName()));
        $result->execute(array($id));
        if ($post = $result->fetchObject()) {
            return new CustomColumn($id, $post->name, $this);
        }
        return NULL;
    }

    protected function getAllCustomValuesFromDatabase()
    {
        $queryFormat = "select {0}.id as id, {0}.value as name, count(*) as count from {0}, {1} where {0}.id = {1}.{2} group by {0}.id, {0}.value order by {0}.value";
        $query = str_format ($queryFormat, $this->getTableName(), $this->getTableLinkName(), $this->getTableLinkColumn());

        $result = parent::getDb()->query($query);
        $entryArray = array();
        while ($post = $result->fetchObject())
        {
            $entryPContent = str_format(localize("bookword", $post->count), $post->count);
            $entryPLinkArray = array(new LinkNavigation ($this->getUri($post->id)));

            $entry = new Entry($post->name, $this->getEntryId($post->id), $entryPContent, $this->datatype, $entryPLinkArray, "", $post->count);

            array_push($entryArray, $entry);
        }
        return $entryArray;
    }

    public function getDescription()
    {
        $desc = $this->getDatabaseDescription();
        if ($desc == NULL || empty($pcontent)) $desc = str_format(localize("customcolumn.description"), $this->getTitle());
        return $desc;
    }

    public function getCustomByBook($book)
    {
        $queryFormat = "select {0}.id as id, {1}.{2} as name from {0}, {1} where {0}.id = {1}.{2} and {1}.book = {3} order by {0}.value";
        $query = str_format ($queryFormat, $this->getTableName(), $this->getTableLinkName(), $this->getTableLinkColumn(), $book->id);

        $result = parent::getDb()->query($query);
        if ($post = $result->fetchObject()) {
            return new CustomColumn($post->id, $post->name, $this);
        }
        return new CustomColumn(NULL, "", $this);
    }
}

class CustomColumnTypeSeries extends CustomColumnType
{
    protected function __construct($pcustomId) {
        parent::__construct($pcustomId, self::CUSTOM_TYPE_SERIES);
    }

    public function getTableName() {
        return "custom_column_{$this->customId}";
    }

    public function getTableLinkName() {
        return "books_custom_column_{$this->customId}_link";
    }

    public function getTableLinkColumn() {
        return "value";
    }

    public function getQuery($id) {
        $query = str_format(Book::SQL_BOOKS_BY_CUSTOM, "{0}", "{1}", $this->getTableLinkName(), $this->getTableLinkColumn());
        return array($query, array($id));
    }

    public function getCustom($id) {
        $result = parent::getDb()->prepare(str_format("select id, value as name from {0} where id = ?", $this->getTableName()));
        $result->execute (array ($id));
        if ($post = $result->fetchObject ()) {
            return new CustomColumn($id, $post->name, $this);
        }
        return NULL;
    }

    protected function getAllCustomValuesFromDatabase()
    {
        $queryFormat = "select {0}.id as id, {0}.value as name, count(*) as count from {0}, {1} where {0}.id = {1}.{2} group by {0}.id, {0}.value order by {0}.value";
        $query = str_format ($queryFormat, $this->getTableName(), $this->getTableLinkName(), $this->getTableLinkColumn());

        $result = parent::getDb()->query($query);
        $entryArray = array();
        while ($post = $result->fetchObject())
        {
            $entryPContent = str_format(localize("bookword", $post->count), $post->count);
            $entryPLinkArray = array(new LinkNavigation($this->getUri($post->id)));

            $entry = new Entry($post->name, $this->getEntryId($post->id), $entryPContent, $this->datatype, $entryPLinkArray, "", $post->count);

            array_push($entryArray, $entry);
        }
        return $entryArray;
    }

    public function getDescription()
    {
        return str_format(localize("customcolumn.description.series", $this->getDistinctValueCount()), $this->getDistinctValueCount());
    }

    public function getCustomByBook($book)
    {
        $queryFormat = "select {0}.id as id, {1}.{2} as name, {1}.extra as extra from {0}, {1} where {0}.id = {1}.{2} and {1}.book = {3}";
        $query = str_format ($queryFormat, $this->getTableName(), $this->getTableLinkName(), $this->getTableLinkColumn(), $book->id);

        $result = parent::getDb()->query($query);
        if ($post = $result->fetchObject()) {
            return new CustomColumn($post->id, $post->name . " [" . $post->extra . "]", $this);
        }
        return new CustomColumn(NULL, "", $this);
    }
}

class CustomColumnTypeEnumeration extends CustomColumnType
{
    protected function __construct($pcustomId) {
        parent::__construct($pcustomId, self::CUSTOM_TYPE_ENUM);
    }

    public function getTableName() {
        return "custom_column_{$this->customId}";
    }

    public function getTableLinkName() {
        return "books_custom_column_{$this->customId}_link";
    }

    public function getTableLinkColumn() {
        return "value";
    }

    public function getQuery($id) {
        $query = str_format(Book::SQL_BOOKS_BY_CUSTOM, "{0}", "{1}", $this->getTableLinkName(), $this->getTableLinkColumn());
        return array($query, array($id));
    }

    public function getCustom($id) {
        $result = parent::getDb ()->prepare(str_format("select id, value as name from {0} where id = ?", $this->getTableName()));
        $result->execute (array ($id));
        if ($post = $result->fetchObject ()) {
            return new CustomColumn ($id, $post->name, $this);
        }
        return NULL;
    }

    protected function getAllCustomValuesFromDatabase()
    {
        $queryFormat = "select {0}.id as id, {0}.value as name, count(*) as count from {0}, {1} where {0}.id = {1}.{2} group by {0}.id, {0}.value order by {0}.value";
        $query = str_format ($queryFormat, $this->getTableName(), $this->getTableLinkName(), $this->getTableLinkColumn());

        $result = parent::getDb()->query($query);
        $entryArray = array();
        while ($post = $result->fetchObject())
        {
            $entryPContent = str_format (localize("bookword", $post->count), $post->count);
            $entryPLinkArray = array(new LinkNavigation ($this->getUri($post->id)));

            $entry = new Entry ($post->name, $this->getEntryId($post->id), $entryPContent, $this->datatype, $entryPLinkArray, "", $post->count);

            array_push ($entryArray, $entry);
        }
        return $entryArray;
    }

    public function getDescription()
    {
        return str_format(localize("customcolumn.description.enum", $this->getDistinctValueCount()), $this->getDistinctValueCount());
    }

    public function getCustomByBook($book)
    {
        $queryFormat = "select {0}.id as id, {1}.{2} as name from {0}, {1} where {0}.id = {1}.{2} and {1}.book = {3}";
        $query = str_format ($queryFormat, $this->getTableName(), $this->getTableLinkName(), $this->getTableLinkColumn(), $book->id);

        $result = parent::getDb()->query($query);
        if ($post = $result->fetchObject()) {
            return new CustomColumn($post->id, $post->name, $this);
        }
        return new CustomColumn(NULL, localize("customcolumn.enum.unknown"), $this);
    }
}

class CustomColumnTypeDate extends CustomColumnType
{
    protected function __construct($pcustomId) {
        parent::__construct($pcustomId, self::CUSTOM_TYPE_DATE);
    }

    public function getTableName() {
        return "custom_column_{$this->customId}";
    }

    public function getTableLinkName() {
        return NULL;
    }

    public function getTableLinkColumn() {
        return NULL;
    }

    public function getQuery($id) {
        $date = new DateTime($id);
        $query = str_format(Book::SQL_BOOKS_BY_CUSTOM_DATE, "{0}", "{1}", $this->getTableName());
        return array($query, array($date->format("Y-m-d")));
    }

    public function getCustom($id) {
        $date = new DateTime($id);

        return new CustomColumn ($id, $date->format(localize("customcolumn.date.format")), $this);
    }

    protected function getAllCustomValuesFromDatabase()
    {
        $queryFormat = "select date(value) as datevalue, count(*) as count from {0} group by datevalue";
        $query = str_format ($queryFormat, $this->getTableName());
        $result = parent::getDb()->query($query);

        $entryArray = array();
        while ($post = $result->fetchObject())
        {
            $date = new DateTimeImmutable($post->datevalue);
            $id = $date->format("Y-m-d");

            $entryPContent = str_format(localize("bookword", $post->count), $post->count);
            $entryPLinkArray = array(new LinkNavigation ($this->getUri($id)));

            $entry = new Entry($date->format(localize("customcolumn.date.format")), $this->getEntryId($id), $entryPContent, $this->datatype, $entryPLinkArray, "", $post->count);

            array_push($entryArray, $entry);
        }

        return $entryArray;
    }

    public function getDescription()
    {
        $desc = $this->getDatabaseDescription();
        if ($desc == NULL || empty($pcontent)) $desc = str_format(localize("customcolumn.description"), $this->getTitle());
        return $desc;
    }

    public function getCustomByBook($book)
    {
        $queryFormat = "select date({0}.value) as datevalue from {0} where {0}.book = {1}";
        $query = str_format ($queryFormat, $this->getTableName(), $book->id);

        $result = parent::getDb()->query($query);
        if ($post = $result->fetchObject()) {
            $date = new DateTimeImmutable($post->datevalue);

            return new CustomColumn($date->getTimestamp(), $date->format(localize("customcolumn.date.format")), $this);
        }
        return new CustomColumn(NULL, localize("customcolumn.date.unknown"), $this);
    }
}

class CustomColumnTypeRating extends CustomColumnType
{
    protected function __construct($pcustomId) {
        parent::__construct($pcustomId, self::CUSTOM_TYPE_RATING);
    }

    public function getTableName() {
        return "custom_column_{$this->customId}";
    }

    public function getTableLinkName() {
        return "books_custom_column_{$this->customId}_link";
    }

    public function getTableLinkColumn() {
        return "value";
    }

    public function getQuery($id) {
        if ($id == 0) {
            $query = str_format(Book::SQL_BOOKS_BY_CUSTOM_RATING_NULL, "{0}", "{1}", $this->getTableLinkName(), $this->getTableName(), $this->getTableLinkColumn());
            return array($query, array());
        } else {
            $query = str_format(Book::SQL_BOOKS_BY_CUSTOM_RATING, "{0}", "{1}", $this->getTableLinkName(), $this->getTableName(), $this->getTableLinkColumn());
            return array($query, array($id));
        }
    }

    public function getCustom($id) {
        return new CustomColumn ($id, str_format(localize("customcolumn.stars", $id/2), $id/2), $this);
    }

    protected function getAllCustomValuesFromDatabase()
    {
        $queryFormat = "select coalesce({0}.value, 0) as value, count(*) as count from books  left join {1} on  books.id = {1}.book left join {0} on {0}.id = {1}.value group by coalesce({0}.value, -1)";
        $query = str_format ($queryFormat, $this->getTableName(), $this->getTableLinkName());
        $result = parent::getDb()->query($query);

        $countArray = array(0=>0, 2=>0, 4=>0, 6=>0, 8=>0, 10=>0);
        while ($row = $result->fetchObject()) {
            $countArray[$row->value] = $row->count;
        }

        $entryArray = array();

        for ($i = 0; $i <= 5; $i++) {
            $count = $countArray[$i*2];
            $name = str_format(localize("customcolumn.stars", $i), $i);
            $entryid = $this->getEntryId($i*2);
            $content = str_format(localize("bookword", $count), $count);
            $linkarray = array(new LinkNavigation($this->getUri($i*2)));
            $entry = new Entry($name, $entryid, $content, $this->datatype, $linkarray, "", $count);
            array_push($entryArray, $entry);
        }

        return $entryArray;
    }

    public function getDescription()
    {
        return localize("customcolumn.description.rating");
    }

    public function getCustomByBook($book)
    {
        $queryFormat = "select {0}.value as value from {0}, {1} where {0}.id = {1}.{2} and {1}.book = {3}";
        $query = str_format ($queryFormat, $this->getTableName(), $this->getTableLinkName(), $this->getTableLinkColumn(), $book->id);

        $result = parent::getDb()->query($query);
        if ($post = $result->fetchObject()) {
            return new CustomColumn($post->value, str_format(localize("customcolumn.stars", $post->value/2), $post->value/2), $this);
        }
        return new CustomColumn(NULL, localize("customcolumn.rating.unknown"), $this);
    }
}

class CustomColumnTypeBool extends CustomColumnType
{
    // PHP pre 5.6 does not support const arrays
    private $BOOLEAN_NAMES = array(
        -1 => "customcolumn.boolean.unknown", // localize("customcolumn.boolean.unknown")
        00 => "customcolumn.boolean.no",      // localize("customcolumn.boolean.no")
        +1 => "customcolumn.boolean.yes",     // localize("customcolumn.boolean.yes")
    );

    protected function __construct($pcustomId) {
        parent::__construct($pcustomId, self::CUSTOM_TYPE_BOOL);
    }

    public function getTableName() {
        return "custom_column_{$this->customId}";
    }

    public function getTableLinkName() {
        return "books_custom_column_{$this->customId}_link";
    }

    public function getTableLinkColumn() {
        return NULL;
    }

    public function getQuery($id) {
        if ($id == -1) {
            $query = str_format(Book::SQL_BOOKS_BY_CUSTOM_BOOL_NULL, "{0}", "{1}", $this->getTableName());
            return array($query, array());
        } else if ($id == 0) {
            $query = str_format(Book::SQL_BOOKS_BY_CUSTOM_BOOL_FALSE, "{0}", "{1}", $this->getTableName());
            return array($query, array());
        } else if ($id == 1) {
            $query = str_format(Book::SQL_BOOKS_BY_CUSTOM_BOOL_TRUE, "{0}", "{1}", $this->getTableName());
            return array($query, array());
        } else {
            return NULL;
        }
    }

    public function getCustom($id) {
        return new CustomColumn ($id, localize($this->BOOLEAN_NAMES[$id]), $this);
    }

    protected function getAllCustomValuesFromDatabase()
    {
        $queryFormat = "select coalesce({0}.value, -1) as id, count(*) as count from books left join {0} on  books.id = {0}.book group by {0}.value order by {0}.value";
        $query = str_format ($queryFormat, $this->getTableName());
        $result = parent::getDb()->query($query);

        $entryArray = array();
        while ($post = $result->fetchObject())
        {
            $entryPContent = str_format(localize("bookword", $post->count), $post->count);
            $entryPLinkArray = array(new LinkNavigation ($this->getUri($post->id)));

            $entry = new Entry(localize($this->BOOLEAN_NAMES[$post->id]), $this->getEntryId($post->id), $entryPContent, $this->datatype, $entryPLinkArray, "", $post->count);

            array_push($entryArray, $entry);
        }
        return $entryArray;
    }

    public function getDescription()
    {
        return localize("customcolumn.description.bool");
    }

    public function getCustomByBook($book)
    {
        $queryFormat = "select {0}.value as boolvalue from {0} where {0}.book = {1}";
        $query = str_format ($queryFormat, $this->getTableName(), $book->id);

        $result = parent::getDb()->query($query);
        if ($post = $result->fetchObject()) {
            return new CustomColumn($post->boolvalue, localize($this->BOOLEAN_NAMES[$post->boolvalue]), $this);
        } else {
            return new CustomColumn(-1, localize($this->BOOLEAN_NAMES[-1]), $this);
        }
    }
}

class CustomColumnTypeInteger extends CustomColumnType
{
    protected function __construct($pcustomId) {
        parent::__construct($pcustomId, self::CUSTOM_TYPE_INT);
    }

    public function getTableName() {
        return "custom_column_{$this->customId}";
    }

    public function getTableLinkName() {
        return NULL;
    }

    public function getTableLinkColumn() {
        return NULL;
    }

    public function getQuery($id) {
        $query = str_format(Book::SQL_BOOKS_BY_CUSTOM_DIRECT, "{0}", "{1}", $this->getTableName());
        return array($query, array($id));
    }

    public function getCustom($id) {
        return new CustomColumn($id, $id, $this);
    }

    protected function getAllCustomValuesFromDatabase()
    {
        $queryFormat = "select value as id, count(*) as count from {0} group by value";
        $query = str_format ($queryFormat, $this->getTableName());

        $result = parent::getDb()->query($query);
        $entryArray = array();
        while ($post = $result->fetchObject())
        {
            $entryPContent = str_format(localize("bookword", $post->count), $post->count);
            $entryPLinkArray = array(new LinkNavigation($this->getUri($post->id)));

            $entry = new Entry($post->id, $this->getEntryId($post->id), $entryPContent, $this->datatype, $entryPLinkArray, "", $post->count);

            array_push($entryArray, $entry);
        }
        return $entryArray;
    }

    public function getDescription()
    {
        $desc = $this->getDatabaseDescription();
        if ($desc == NULL || empty($pcontent)) $desc = str_format(localize("customcolumn.description"), $this->getTitle());
        return $desc;
    }

    public function getCustomByBook($book)
    {
        $queryFormat = "select {0}.value as value from {0} where {0}.book = {1}";
        $query = str_format ($queryFormat, $this->getTableName(), $book->id);

        $result = parent::getDb()->query($query);
        if ($post = $result->fetchObject()) {
            return new CustomColumn($post->value, $post->value, $this);
        }
        return new CustomColumn(NULL, localize("customcolumn.int.unknown"), $this);
    }
}

class CustomColumnTypeFloat extends CustomColumnType
{
    protected function __construct($pcustomId) {
        parent::__construct($pcustomId, self::CUSTOM_TYPE_FLOAT);
    }

    public function getTableName() {
        return "custom_column_{$this->customId}";
    }

    public function getTableLinkName() {
        return NULL;
    }

    public function getTableLinkColumn() {
        return NULL;
    }

    public function getQuery($id) {
        $query = str_format(Book::SQL_BOOKS_BY_CUSTOM_DIRECT, "{0}", "{1}", $this->getTableName());
        return array($query, array($id));
    }

    public function getCustom($id) {
        return new CustomColumn($id, $id, $this);
    }

    protected function getAllCustomValuesFromDatabase()
    {
        $queryFormat = "select value as id, count(*) as count from {0} group by value";
        $query = str_format ($queryFormat, $this->getTableName());

        $result = parent::getDb()->query($query);
        $entryArray = array();
        while ($post = $result->fetchObject())
        {
            $entryPContent = str_format(localize("bookword", $post->count), $post->count);
            $entryPLinkArray = array(new LinkNavigation($this->getUri($post->id)));

            $entry = new Entry($post->id, $this->getEntryId($post->id), $entryPContent, $this->datatype, $entryPLinkArray, "", $post->count);

            array_push($entryArray, $entry);
        }
        return $entryArray;
    }

    public function getDescription()
    {
        $desc = $this->getDatabaseDescription();
        if ($desc == NULL || empty($pcontent)) $desc = str_format(localize("customcolumn.description"), $this->getTitle());
        return $desc;
    }

    public function getCustomByBook($book)
    {
        $queryFormat = "select {0}.value as value from {0} where {0}.book = {1}";
        $query = str_format ($queryFormat, $this->getTableName(), $book->id);

        $result = parent::getDb()->query($query);
        if ($post = $result->fetchObject()) {
            return new CustomColumn($post->value, $post->value, $this);
        }
        return new CustomColumn(NULL, localize("customcolumn.float.unknown"), $this);
    }
}
