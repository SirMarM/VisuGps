<?php
/*
Script: vg_parser.php
        GPS track file parsers.

License: GNU General Public License

This file is part of VisuGps

VisuGps is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

VisuGps is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with VisuGps; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

Copyright (c) 2007-2011 Victor Berchet, <http://www.victorb.fr>

Credits:
    - Some of GPX, NMEA and TRK parsing routines are from Emmanuel Chabani <mans@parawing.net>
*/

/*
Function: ParseIgc
        Parse a GPS track - IGC format

Arguments:
        trackFile - input track file
        trackData - output track (associative array)

Returns:
        The number of points of the track.
        0 if the track format is not recognized
*/
function ParseIgc($trackFile, &$trackData)
{
    if (preg_match('/(?:HFDTE(?:DATE:)?)(\d{2})(\d{2})(\d{2})/mi', $trackFile, $m)) {
        $trackData['date']['day'] = intval($m[1]);
        $trackData['date']['month'] = intval($m[2]);
        $trackData['date']['year'] = intval($m[3]) + (($m[3] > 60)?1900:2000);
    }

    if (preg_match('/^HFPLTPILOT:(.*)$/mi', $trackFile, $m)) {
        $trackData['pilot'] = htmlentities(trim($m[1]));
    }

    $nbPts = 0;
  
    foreach(preg_split('/[\n\r]+/', $trackFile, null, PREG_SPLIT_NO_EMPTY) as $line) {
        if (
            preg_match(
                '/B
                (?P<hour>\d{2})(?P<min>\d{2})(?P<sec>\d{2})
                (?P<latE>\d{2})(?P<latD>\d{5})(?P<latS>\w)
                (?P<lonE>\d{3})(?P<lonD>\d{5})(?P<lonS>\w).
                (?P<elevP>\d{5}|(-\d{4}))(?P<elevG>\d{5})
                /xim',
                $line,
                $m)
        ) {
            $latD = floatval($m['latD']) / 60000;
            $lonD = floatval($m['lonD']) / 60000;
            $trackData['lat'][$nbPts] = floatval($m['latE'] + $latD) * (strtoupper($m['latS']) == 'N'? 1 : -1);
            $trackData['lon'][$nbPts] = floatval($m['lonE'] + $lonD) * (strtoupper($m['lonS']) == 'E'? 1 : -1);
            $trackData['elev'][$nbPts] = intval($m['elevG']);
            $trackData['time']['hour'][$nbPts] = intval($m['hour']);
            $trackData['time']['min'][$nbPts] = intval($m['min']);
            $trackData['time']['sec'][$nbPts] = intval($m['sec']);
            $nbPts++;
        }        
    }

    return $nbPts;
}

/*
Function: ParseOzi
        Parse a GPS track - OziExplorer PLT format

See: http://www.rus-roads.ru/gps/help_ozi/fileformats.html

Arguments:
        trackFile - input track file
        trackData - output track (associative array)

Returns:
        The number of points of the track.
        0 if the track format is not recognized
*/
function ParseOzi($trackFile, &$trackData)
{
    if (!preg_match('/OziExplorer/i', $trackFile, $m)) {
        return 0;
    }

    $nbPts = 0;

    foreach(preg_split('/[\n\r]+/', $trackFile, null, PREG_SPLIT_NO_EMPTY) as $line) {
        if (
            preg_match(
                '/^\s+
                (?P<lat>[-\d\.]+)[,\s]+
                (?P<lon>[-\d\.]+)[,\s]+[01][,\s]+
                (?P<elev>[-\d\.]+)[,\s]+
                (?P<date>[\d\.]+).*$
                /xim',
                $line,
                $m
            )
        ) {
            $trackData['lat'][$nbPts] = floatval($m['lat']);
            $trackData['lon'][$nbPts] = floatval($m['lon']);
            $trackData['elev'][$nbPts] = max(intval($m['elev'] * 0.3048), 0);

            $time = floatval($m['date']) - intval($m['date']);
            $time = $time * 24;
            $hour = intval($time);
            $time = ($time - $hour) * 60;
            $min = intval($time);
            $time = ($time - $min) * 60;
            $sec = intval($time);
            $trackData['time']['hour'][$nbPts] = $hour;
            $trackData['time']['min'][$nbPts] = $min;
            $trackData['time']['sec'][$nbPts] = $sec;
            $nbPts++;
        }
    }

    if ($nbPts > 5) {
        $date = date_create();
        date_date_set($date, 1899, 12, 30);
        date_modify($date, intval($m['date']) . ' days');
        $trackData['date']['day'] = intval(date_format($date, 'j'));
        $trackData['date']['month'] = intval(date_format($date, 'n'));
        $trackData['date']['year'] = intval(date_format($date, 'Y'));
    }

    return $nbPts;
}

/*
Function: ParseTrk
        Parse a GPS track - TRK format

Arguments:
        trackFile - input track file
        trackData - output track (associative array)

Returns:
        The number of points of the track.
        0 if the track format is not recognized
*/
function ParseTrk($trackFile, &$trackData)
{
    $nbPts = 0;

    foreach(preg_split('/[\n\r]+/', $trackFile, null, PREG_SPLIT_NO_EMPTY) as $line) {
        if (
            // T  A 49.34586726ºN 0.72568615ºW 01-NOV-10 15:54:34.000 N 51.6 0.0 0.1 0.0 0 -1000.0 9999999562023526247000000.0 -1 60.9 -1.0
            preg_match(
                '/^T\s+A\s+
                (?P<lat>[0-9.]+).*?(?P<latS>[NS])\s+
                (?P<lon>[0-9.]+).*?(?P<lonS>[EW])\s+
                (?P<day>\d{2})-(?P<month>\w{3})-(?P<year>\d{2})\s+
                (?P<hour>\d{2}):(?P<min>\d{2}):(?P<sec>\d{2})\.\d+\s+.\s+
                (?P<elev>\d+)
                /xim',
                $line,
                $m
            ) ||
            // T  N45.6321216 E003.1162763 19-JUL-10 14:33:59 00785
            preg_match(
                '/^T\s+
                (?P<latS>[NS])(?P<lat>[0-9.]+)\s+
                (?P<lonS>[EW])(?P<lon>[0-9.]+)\s+
                (?P<day>\d{2})-(?P<month>\w{3})-(?P<year>\d{2})\s+
                (?P<hour>\d{2}):(?P<min>\d{2}):(?P<sec>\d{2})\s+
                (?P<elev>\d+)
                /xim',
                $line,
                $m
            )
        ) {
            $trackData['lat'][$nbPts] = ($m['lat']) * (strtoupper($m['latS']) == 'N' ? 1 : -1);
            $trackData['lon'][$nbPts] = ($m['lon']) * (strtoupper($m['lonS']) == 'E' ? 1 : -1);
            $trackData['elev'][$nbPts] = intval($m['elev']);
            $trackData['time']['hour'][$nbPts] = intval($m['hour']);
            $trackData['time']['min'][$nbPts] = intval($m['min']);
            $trackData['time']['sec'][$nbPts] = intval($m['sec']);
            $nbPts++;
        }
    }

    if ($nbPts > 5) {
        $months = array('JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
                        'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12);
        $trackData['date']['day'] = intval($m['day']);
        $month = strtoupper($m['month']);
        $trackData['date']['month'] = in_array($month, $months) ? $months[$month] : 1;
        $trackData['date']['year'] = intval($m['year']) + (($m['year'] > 60)?1900:2000);
    }

    return $nbPts;
}

/*
Function: ParseNmea
        Parse a GPS track - NMEA format

Arguments:
        trackFile - input track file
        trackData - output track (associative array)

Returns:
        The number of points of the track.
        0 if the track format is not recognized
*/
function ParseNmea($trackFile, &$trackData)
{
    // $GPRMC,134329.000,V,4902.174,N,00132.360,E,0.00,0.00,030910,,*19
    if (preg_match('
      /^\$GPRMC,
      [\d.]+,
      .,
      [\d.]+,
      .,
      [\d.]+,
      .,
      [\d.]+,
      [\d.]+,
      (?P<day>\d{2})(?P<month>\d{2})(?P<year>\d{2})
      /xim', $trackFile, $m))
    {
        $trackData['date']['day'] = intval($m['day']);
        $trackData['date']['month'] = intval($m['month']);
        $trackData['date']['year'] = intval($m['year']) + (($m['year'] > 60)? 1900 : 2000);
    }

    $nbPts = 0;

    foreach(preg_split('/[\n\r]+/', $trackFile, null, PREG_SPLIT_NO_EMPTY) as $line) {
        if (
            // $GPGGA,134329.000,4902.174,N,00132.360,E,0,00,0.0,119.000,M,0.0,M,,*62
            preg_match(
                '/^\$GPGGA,
                (?P<hour>\d{2})(?P<min>\d{2})(?P<sec>\d{2})[\d.]*,
                (?P<lat>[\d.]+),(?P<latS>[NS]),
                (?P<lon>[\d.]+),(?P<lonS>[EW]),
                \d+,
                \d+,
                [\d.]+,
                (?P<elev>[\d.]+)
                /xim',
                $line,
                $m
            )
        ) {
            $lonDeg= intval($m['lon'] / 100);
            $lonMin= $m['lon'] - $lonDeg * 100;
            $latDeg= intval($m['lat'] / 100);
            $latMin= $m['lat'][$nbPts] - $latDeg * 100;
            $trackData['lat'][$nbPts] = ($latDeg + $latMin / 60) * (strtoupper($m['latS']) == 'N'? 1 : -1);
            $trackData['lon'][$nbPts] = ($lonDeg + $lonMin / 60) * (strtoupper($m['lonS']) == 'E'? 1 : -1);
            $trackData['elev'][$nbPts] = intval($m['elev']);
            $trackData['time']['hour'][$nbPts] = intval($m['hour']);
            $trackData['time']['min'][$nbPts] = intval($m['min']);
            $trackData['time']['sec'][$nbPts] = intval($m['sec']);
            $nbPts++;
        }
    }

    return $nbPts;
}

/*
Function: ParseGpx
        Parse a GPS track - GPX format

Arguments:
        trackFile - input track file
        trackData - output track (associative array)

Returns:
        The number of points of the track.
        0 if the track format is not recognized
*/
function ParseGpx($trackFile, &$trackData)
{
    if (!($xml = @simplexml_load_string($trackFile))) return 0;

    if (!isset($xml->trk[0]->trkseg[0]->trkpt[0])) return 0;

    $dateSet = false;
    $i = $ptLat = $ptLon = $ptElev = $ptHour = $ptMin = $ptSec = 0;

    $trkIdx = $gpsTrkIdx = 0;
    foreach ($xml->trk as $track) {
        if (isset($track->name) &&
            (strtoupper($track->name) === 'GNSSALTTRK')) {
            $gpsTrkIdx = $trkIdx;
            break;
        }
        $trkIdx++;
    }

    foreach ($xml->trk[$gpsTrkIdx]->trkseg as $trackSeg) {
        foreach ($trackSeg->trkpt as $trackPt) {
            $atr = $trackPt->attributes();
            if (isset($atr->lat)) $ptLat = floatval($atr->lat);
            if (isset($atr->lon)) $ptLon = floatval($atr->lon);

            if (isset($trackPt->ele)) $ptElev = round($trackPt->ele);
            if (isset($trackPt->time)) {
                if (preg_match('/(?P<h>\d{2}):(?P<m>\d{2}):(?P<s>\d{2})/', $trackPt->time, $m)) {
                    $ptHour = intval($m['h']);
                    $ptMin = intval($m['m']);
                    $ptSec = intval($m['s']);
                }
                if (!$dateSet &&
                    preg_match('/(?P<y>\d{4})-(?P<m>\d{2})-(?P<d>\d{2})/', $trackPt->time, $m)) {
                    $dateSet = true;
                    $trackData['date']['year'] = intval($m['y']);
                    $trackData['date']['month'] = intval($m['m']);
                    $trackData['date']['day'] = intval($m['d']);
                }
            }
            $trackData['lat'][$i] = $ptLat;
            $trackData['lon'][$i] = $ptLon;
            $trackData['elev'][$i] = $ptElev;
            $trackData['time']['hour'][$i] = $ptHour;
            $trackData['time']['min'][$i] = $ptMin;
            $trackData['time']['sec'][$i] = $ptSec;
            $i++;
        }
    }

    $trackData['nbPt'] = $i;

    return $i;
}

/*
Function: IsKml
        Detect KML file format

Arguments:
        trackFile - input track file

Returns:
        true if the file is a valid KML file
*/
function IsKml($trackFile)
{
    if (preg_match('/xmlns *= *["\']http:\/\/.*?\/kml\/[\d\.]+/im', $trackFile) > 0) {
        return true;
    } elseif (preg_match('/GpsDump/im', $trackFile) > 0 &&
              preg_match('/<LineString>/im', $trackFile) > 0) {
        // GpsDump generates invalid kml files!
        return true;
    }

}
