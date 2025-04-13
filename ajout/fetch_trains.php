<?php
header('Content-Type: application/json');
// Fetches the list of trains from the database
require_once "../database/sncf.php";

$depart_uic = $_GET['depart_uic'] ?? '';
// $gare_depart = 'Lyon Part Dieu';
$arrivee_uic = $_GET['arrivee_uic'] ?? '';
// $gare_arrivee = 'Virieu-le-Grand - Belley';
if (empty($depart_uic) || empty($arrivee_uic)) {
  die("Un code uic de départ et d'arrivée est nécessaire");
}

// Fetch direct trips
$stmtDirect = $mysqli->prepare("
with 
  dest as (select * from stops where stop_id like ?), -- ville d'arrivée
  source as (select * from stops where stop_id like ?), -- ville de départ
  dtrips as (select DISTINCT trip_id from stop_times inner join dest on stop_times.stop_id = dest.stop_id ),
  strips as (select DISTINCT trip_id from stop_times inner join source on stop_times.stop_id = source.stop_id ),
  direct as (
    select distinct t.trip_id, t.service_id, t.trip_headsign from trips t
    where t.trip_id in (select trip_id from dtrips) and t.trip_id in (SELECT trip_id from strips)
  ),
  pc as (select count(*) / 13 as pc, weekday(date(date)) as wd, service_id from calendar_dates group by service_id, weekday(date(date))),
  servfreqs as (
    select service_id,
      sum((wd = 0) * pc) as on_lu,
      sum((wd = 1) * pc) as on_ma,
      sum((wd = 2) * pc) as on_me,
      sum((wd = 3) * pc) as on_je,
      sum((wd = 4) * pc) as on_ve,
      sum((wd = 5) * pc) as on_sa,
      sum((wd = 6) * pc) as on_di
    from pc
    group by service_id
  )
select
  trip_headsign as num_train,
  st.departure_time as depart, 
  dt.arrival_time as arrivee, 
  round(TIME_TO_SEC(TIMEDIFF(TIME(dt.arrival_time), TIME(st.departure_time))) / 60) as duree,
  round(sum(on_lu), 2) as prob_on_lu,
  round(sum(on_ma), 2) as prob_on_ma,
  round(sum(on_me), 2) as prob_on_me,
  round(sum(on_je), 2) as prob_on_je,
  round(sum(on_ve), 2) as prob_on_ve,
  round(sum(on_sa), 2) as prob_on_sa,
  round(sum(on_di), 2) as prob_on_di
from direct d
inner join stop_times st on st.trip_id = d.trip_id and st.stop_id in (select stop_id from source)
inner join stop_times dt on dt.trip_id = d.trip_id and dt.stop_id in (select stop_id from dest) and dt.stop_sequence > st.stop_sequence
inner join servfreqs s on s.service_id = d.service_id
group by trip_headsign
order by depart
;
");

if (!$stmtDirect) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}
$regexarr = "%$arrivee_uic%";
$regexdep = "%$depart_uic%";
$stmtDirect->bind_param("ss", $regexarr, $regexdep);
$stmtDirect->execute();
$resDirect = $stmtDirect->get_result();
$trains = [];
// echo "coucou";
while ($row = $resDirect->fetch_assoc()) {
  // echo $row['trip_id'];
  // $trains[] = $row;
  $trains[] = [
    'num_train' => $row['num_train'],
    'depart' => $row['depart'],
    'arrivee' => $row['arrivee'],
    'via' => 'Direct',
    'duree' => $row['duree'],
    'lundi' => $row['prob_on_lu'] >= 0.9 ? '✅' : ($row['prob_on_lu'] <= 0.05 ? '-' : $row['prob_on_lu'] * 100 . " %"),
    'mardi' => $row['prob_on_ma'] >= 0.9 ? '✅' : ($row['prob_on_ma'] <= 0.05 ? '-' : $row['prob_on_ma'] * 100 . " %"),
    'mercredi' => $row['prob_on_me'] >= 0.9 ? '✅' : ($row['prob_on_me'] <= 0.05 ? '-' : $row['prob_on_me'] * 100 . " %"),
    'jeudi' => $row['prob_on_je'] >= 0.9 ? '✅' : ($row['prob_on_je'] <= 0.05 ? '-' : $row['prob_on_je'] * 100 . " %"),
    'vendredi' => $row['prob_on_ve'] >= 0.9 ? '✅' : ($row['prob_on_ve'] <= 0.05 ? '-' : $row['prob_on_ve'] * 100 . " %"),
    'samedi' => $row['prob_on_sa'] >= 0.9 ? '✅' : ($row['prob_on_sa'] <= 0.05 ? '-' : $row['prob_on_sa'] * 100 . " %"),
    'dimanche' => $row['prob_on_di'] >= 0.9 ? '✅' : ($row['prob_on_di'] <= 0.05 ? '-' : $row['prob_on_di'] * 100 . " %"),
  ];
}
$stmtDirect->close();

if (count($trains) > 15) {
  // return the list of trains
  echo json_encode($trains);
  return;
}

$durMaxCorresp = 50;
// Compute max direct duration = 2 * max(train.duree)
$durMaxTotal = count($trains) > 0 ? 2 * max(array_column($trains, 'duree')) : 1000;

// Fetch 1 corresp trips
$stmtCorresp = $mysqli->prepare("
with 
  dest as (select * from stops where stop_id like ?), -- ville d'arrivée
  source as (select * from stops where stop_id like ?), -- ville de départ
  dtrips as (select DISTINCT trip_id from stop_times inner join dest on stop_times.stop_id = dest.stop_id ),
  strips as (select DISTINCT trip_id from stop_times inner join source on stop_times.stop_id = source.stop_id ),
  nondirect as (
  select distinct t.trip_id, t.service_id from trips t
  where 
    (t.trip_id in (select trip_id from dtrips) and t.trip_id not in (SELECT trip_id from strips))
    or
    (t.trip_id not in (select trip_id from dtrips) and t.trip_id in (SELECT trip_id from strips))
  ),

  dtimes as (
    select
      dest.stop_id,
      dest.stop_name,
      arrival_time,
      departure_time,
      trip_id,
      stop_sequence
    from dest
    left join stop_times st on dest.stop_id = st.stop_id
    where trip_id in (select trip_id from nondirect)
  ),
  stimes as (
    select
      source.stop_id,
      source.stop_name,
      arrival_time,
      departure_time,
      trip_id,
      stop_sequence
    from source
    left join stop_times st on source.stop_id = st.stop_id
    where trip_id in (select trip_id from nondirect)
  ),

  dest_connected_times as (
    select
      corresp.trip_id as trip_2,
      corresp.stop_id as co_id,
      corresp.departure_time as co_dep,
      corresp.stop_sequence as co_2_seq,
      t.stop_id as dest_id,
      t.arrival_time as dest_arrival,
      t.stop_sequence as dest_seq
    from dtimes t
    inner join stop_times corresp on t.trip_id = corresp.trip_id and corresp.stop_sequence < t.stop_sequence
  ),
  source_connected_times as (
    select
      corresp.trip_id as trip_1,
      corresp.stop_id as co_id,
      corresp.arrival_time as co_arrival,
      corresp.stop_sequence as co_1_seq,
      t.stop_id as source_id,
      t.departure_time as source_dep,
      t.stop_sequence as source_seq
    from stimes t
    inner join stop_times corresp on t.trip_id = corresp.trip_id and corresp.stop_sequence > t.stop_sequence
  ),

  possible_trips as (
    select
      t1.trip_1,
      t1.source_dep,
      t1.co_arrival,
      t1.co_id,
      t2.co_dep,
      t2.dest_arrival,
      round(TIME_TO_SEC(TIMEDIFF(TIME(t2.co_dep), TIME(t1.co_arrival))) / 60) as co_dur,
      t2.trip_2
    from dest_connected_times t2
    inner join source_connected_times t1
      on 1 = 1
      and t1.co_id = t2.co_id
      and t1.trip_1 != t2.trip_2
      and t1.co_arrival <= t2.co_dep
      and TIME_TO_SEC(TIMEDIFF(TIME(t2.co_dep), TIME(t1.co_arrival))) / 60 <= ? -- durée max corresp
      and TIME_TO_SEC(TIMEDIFF(TIME(t2.dest_arrival), TIME(t1.source_dep))) / 60 <= ? -- durée max trajet
    order by source_dep asc, co_dep asc
  ),

  pc as (select count(*) / 91 * 7 as pc, weekday(date(date)) as wd, service_id from calendar_dates group by service_id, weekday(date(date))),
  servfreqs as (select service_id,
    sum((wd = 0) * pc) as on_lu,
    sum((wd = 1) * pc) as on_ma,
    sum((wd = 2) * pc) as on_me,
    sum((wd = 3) * pc) as on_je,
    sum((wd = 4) * pc) as on_ve,
    sum((wd = 5) * pc) as on_sa,
    sum((wd = 6) * pc) as on_di
  from pc
  group by service_id),
  tripfreqs as (select trip_id, s.* from nondirect n left join servfreqs s on n.service_id = s.service_id)

select
	source_dep as depart,
	co_arrival,
	GROUP_CONCAT(distinct stops.stop_name separator ' ou ') as stop_name,
	co_dur,
	co_dep,
	dest_arrival as arrivee,
	round(TIME_TO_SEC(TIMEDIFF(TIME(dest_arrival), TIME(source_dep))) / 60) as duree,
	round(sum(f1.on_lu * f2.on_lu), 2) as prob_on_lu,
	round(sum(f1.on_ma * f2.on_ma), 2) as prob_on_ma,
	round(sum(f1.on_me * f2.on_me), 2) as prob_on_me,
	round(sum(f1.on_je * f2.on_je), 2) as prob_on_je,
	round(sum(f1.on_ve * f2.on_ve), 2) as prob_on_ve,
	round(sum(f1.on_sa * f2.on_sa), 2) as prob_on_sa,
	round(sum(f1.on_di * f2.on_di), 2) as prob_on_di
from possible_trips pt
inner join tripfreqs f1 on pt.trip_1 = f1.trip_id
inner join tripfreqs f2 on pt.trip_2 = f2.trip_id
left join stops on stops.stop_id = pt.co_id
group by source_dep, dest_arrival
order by source_dep, co_dep
;
");

if (!$stmtDirect) {
  die("Problème de préparation de la requête : " . $mysqli->error);
}
$stmtCorresp->bind_param("ssii", $regexarr, $regexdep, $durMaxCorresp, $durMaxTotal);
$stmtCorresp->execute();
$resCorresp = $stmtCorresp->get_result();

while ($row = $resCorresp->fetch_assoc()) {
  $trains[] = [
    'depart' => $row['depart'],
    'arrivee' => $row['arrivee'],
    'via' => $row['stop_name'],
    'co_dur' => $row['co_dur'],
    'duree' => $row['duree'],
    'lundi' => $row['prob_on_lu'] >= 0.9 ? '✅' : ($row['prob_on_lu'] <= 0.05 ? '-' : $row['prob_on_lu'] * 100 . " %"),
    'mardi' => $row['prob_on_ma'] >= 0.9 ? '✅' : ($row['prob_on_ma'] <= 0.05 ? '-' : $row['prob_on_ma'] * 100 . " %"),
    'mercredi' => $row['prob_on_me'] >= 0.9 ? '✅' : ($row['prob_on_me'] <= 0.05 ? '-' : $row['prob_on_me'] * 100 . " %"),
    'jeudi' => $row['prob_on_je'] >= 0.9 ? '✅' : ($row['prob_on_je'] <= 0.05 ? '-' : $row['prob_on_je'] * 100 . " %"),
    'vendredi' => $row['prob_on_ve'] >= 0.9 ? '✅' : ($row['prob_on_ve'] <= 0.05 ? '-' : $row['prob_on_ve'] * 100 . " %"),
    'samedi' => $row['prob_on_sa'] >= 0.9 ? '✅' : ($row['prob_on_sa'] <= 0.05 ? '-' : $row['prob_on_sa'] * 100 . " %"),
    'dimanche' => $row['prob_on_di'] >= 0.9 ? '✅' : ($row['prob_on_di'] <= 0.05 ? '-' : $row['prob_on_di'] * 100 . " %"),
  ];
}
$stmtCorresp->close();

// Sort by departure time
usort($trains, function ($a, $b) {
  return $a['depart'] <=> $b['depart'];
});

// return the list of trains
echo json_encode($trains);

?>