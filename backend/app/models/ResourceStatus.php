<?php
// Display-only status: never persisted into Driver/Vehicle operational enums.
class ResourceStatus {
    public const EXTRA = ['pending','accepted'];
    public static function sql(string $alias,string $key):string {
        if(!in_array([$alias,$key],[['d','driver_id'],['v','vehicle_id']],true))throw new LogicException('Invalid resource alias');
        return "CASE WHEN $alias.status IN ('inactive','maintenance') THEN $alias.status ELSE COALESCE((SELECT CASE WHEN t.status='IN_PROGRESS' THEN 'on_trip' WHEN t.status='PLANNED' AND a.accepted_at IS NULL THEN 'pending' WHEN t.status='PLANNED' AND a.accepted_at IS NOT NULL THEN 'accepted' ELSE 'unknown' END FROM assignments a LEFT JOIN trips t ON t.id=a.trip_id WHERE a.$key=$alias.$key AND a.status='assigned' ORDER BY a.assignment_id DESC LIMIT 1),CASE WHEN $alias.status='on_trip' THEN 'inactive' ELSE $alias.status END) END";
    }
    public static function resolve(string $stored,?array $assignment,?array $trip):string {
        if(in_array($stored,['inactive','maintenance'],true))return $stored;
        if(!$assignment||$assignment['status']!=='assigned')return $stored==='on_trip'?'inactive':$stored;
        if(($trip['status']??null)==='IN_PROGRESS')return 'on_trip';
        if(($trip['status']??null)==='PLANNED')return $assignment['accepted_at']===null?'pending':'accepted';
        return 'unknown'; // Still held by the unique active index; never show ready for inconsistent data.
    }
}
