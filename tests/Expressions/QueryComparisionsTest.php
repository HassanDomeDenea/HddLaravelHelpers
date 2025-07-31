<?php

use HassanDomeDenea\HddLaravelHelpers\QueryComparisons\IsTruthy;

it('is truthy works',function (){
    $sql = DB::table('users')->where(new IsTruthy('is_active'))->toRawSql();
    expect($sql)->toBe('select * from "users" where ("is_active" is true)');
    $sql = DB::table('users')->where(new IsTruthy('is_active',false))->toRawSql();
    expect($sql)->toBe('select * from "users" where ("is_active" is false)');
});
