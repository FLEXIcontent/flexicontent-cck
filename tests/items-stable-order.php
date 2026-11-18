<?php
/** The limited Items-manager query needs a unique final ordering key. */
$source=file_get_contents(__DIR__.'/../admin/models/items.php');
$start=strpos($source,'protected function _buildContentOrderBy(');
$method=''; $depth=0; $opened=false;
foreach (token_get_all('<?php '.substr($source,$start)) as $token) {
    if (is_array($token) && $token[0]===T_OPEN_TAG) continue;
    $method.=is_array($token) ? $token[1] : $token;
    if ($token==='{') { ++$depth; $opened=true; }
    if ($token==='}' && --$depth===0 && $opened) break;
}
eval('class ItemsOrderingFixture {
    public $state=array();
    public function getState($key) { return $this->state[$key] ?? null; }
    public function ordering() { return $this->_buildContentOrderBy(); }
    '.$method.'
}');
$model=new ItemsOrderingFixture;
foreach (array('rating','rating_count','a.title','a.modified','type_name','a.ordering','catsordering','a.id') as $order) {
    foreach (array('ASC','DESC') as $direction) {
        $model->state=array('filter_order'=>$order,'filter_order_Dir'=>$direction);
        $sql=preg_replace('/\s+/', ' ', $model->ordering());
        if (strpos($sql,'a.id')===false) throw new RuntimeException('No unique tie-breaker for '.$order);
        if ($order==='rating' && strpos($sql,', cr.rating_count '.$direction)===false) throw new RuntimeException('Rating count secondary order lost');
        if ($order==='a.id' && $sql!==' ORDER BY a.id '.$direction) throw new RuntimeException('ID direction changed');
    }
}
echo "16 stable item ordering checks passed\n";
