function showNoItemLabel($target)
{
    $liTarget = $('.' + $target + ' .no-content');
    $ul = $liTarget.parent();
    if ($ul.find('li').size() == 1)
    {
        $liTarget.removeClass('hide');
    }
}
