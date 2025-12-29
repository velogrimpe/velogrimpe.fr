//  --- Ajout des lignes de train ---
const paintRules = [
  {
    dataLayer: "ter",
    symbolizer: new protomapsL.LineSymbolizer({
      color: "#000",
      width: (z) => (z <= 6 ? 0.5 : z < 9 ? 1 : 1.5),
    }),
  },
];
const trainlinesLayer = protomapsL.leafletLayer({
  url: "/bdd/trains/ter.pmtiles",
  paintRules,
  maxDataZoom: 16,
  pane: "overlayPane",
  attribution: "SNCF",
});
//  --- Ajout des lignes de TGV ---
const tgvPaintRules = [
  {
    dataLayer: "tgv",
    symbolizer: new protomapsL.LineSymbolizer({
      color: "#a00",
      width: (z) => (z <= 6 ? 0.5 : z < 9 ? 1 : 1.5),
    }),
  },
];
const tgvShowLabelFromZoom = 14;
const tgvLabelRules = [
  {
    dataLayer: "tgv",
    symbolizer: new protomapsL.GroupSymbolizer([
      new protomapsL.CircleSymbolizer({
        radius: 3,
        fill: "#a00",
        stroke: "#fff",
        width: 1,
      }),
      new protomapsL.OffsetTextSymbolizer({
        label_props: ["name"],
        fill: (z, f) => (z < tgvShowLabelFromZoom ? "transparent" : "#a00"),
        stroke: "white",
        width: (z, f) => (z < tgvShowLabelFromZoom ? 0 : 2),
        maxLineChars: 15,
        lineHeight: 1.2,
        placements: [protomapsL.TextPlacements.S],
        offsetY: 1,
        justify: 2,
        font: (z, f) => {
          return "700 14px sans-serif";
        },
      }),
    ]),
  },
];
const tgvLayer = protomapsL.leafletLayer({
  url: "/bdd/trains/tgv.pmtiles",
  paintRules: tgvPaintRules,
  labelRules: tgvLabelRules,
  maxDataZoom: 16,
  pane: "overlayPane",
  attribution: "SNCF",
});
//  --- Ajout des campings ---
const sheetIconSize = 40;
// const showLabelFromZoom = 8;
const showLabelFromZoom = 14;
const ICONS = `
<html><body>
  <svg
    id="camping"
    width=${sheetIconSize}
    height=${sheetIconSize}
   viewBox="0 0 393.30876 395.01346"
   version="1.1"
   id="svg6"
   sodipodi:docname="camping_basic.svg"
   inkscape:version="1.4.2 (ebf0e940, 2025-05-08)"
   xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape"
   xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd"
   xmlns="http://www.w3.org/2000/svg"
   xmlns:svg="http://www.w3.org/2000/svg">
  <defs
     id="defs6" />
  <sodipodi:namedview
     id="namedview6"
     pagecolor="#85c67e"
     bordercolor="#000000"
     borderopacity="0.25"
     inkscape:showpageshadow="2"
     inkscape:pageopacity="0.0"
     inkscape:pagecheckerboard="0"
     inkscape:deskcolor="#d1d1d1"
     inkscape:zoom="1.4004132"
     inkscape:cx="212.79434"
     inkscape:cy="95.686045"
     inkscape:window-width="1680"
     inkscape:window-height="997"
     inkscape:window-x="0"
     inkscape:window-y="25"
     inkscape:window-maximized="1"
     inkscape:current-layer="svg6"
     showgrid="false" />
  <path
     style="fill:none;stroke:#ffffff;stroke-width:75;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:none;stroke-opacity:1"
     d="m 41.597778,356.91655 c -6.775394,-15.19428 -3.542146,-39.06349 -1.224161,-42.36176 9.102108,-0.25711 17.510261,-0.29847 17.510261,-0.29847 L 180.95193,97.037835 c 0,0 -26.22762,-34.970157 -26.22762,-45.057702 0,-10.087546 14.12257,-18.157582 20.17509,-12.777558 6.05253,5.380025 19.50259,29.590133 19.50259,29.590133 0,0 13.45006,-19.502587 17.48508,-24.210108 4.03502,-4.707522 23.99126,-0.668517 21.30125,11.436537 -2.69001,12.105054 -20.62875,41.018698 -20.62875,41.018698 L 336.30013,313.58381 c 0,0 9.16353,-2.44326 13.68087,1.34899 4.51734,3.79225 5.82172,20.84361 5.82172,20.84361 0,0 0.27003,16.09237 -3.58137,20.62475 -65.10713,2.06974 -310.623572,0.51539 -310.623572,0.51539 z"
     id="path6"
     sodipodi:nodetypes="ccccsscsscccccc" />
  <path
     d="m 183.24326,354.32397 c 0,-1 -0.47875,-1.28876 -1.97875,-2.48876 -4.05351,-0.18312 -2.03734,-0.81903 -68.13733,-0.81903 -78.000002,0 -70.422117,7.02288 -71.717599,-19.6938 -0.839125,-17.30526 -15.837337,-12.77182 139.962649,-12.77182 127.2,0 152.21129,-0.5693 152.91129,1.13073 6.21609,5.25382 8.03197,25.80536 3.16731,30.41022 -0.6,1.6 -23.82427,1.02796 -73.42337,1.32633 l -54.34607,0.32693 -1.15393,0.827 c -1.60929,1.15335 -0.49003,0.0951 -0.49003,0.89509 0,1.3 -5.29907,0.97528 -13.19907,0.97528 M 191.1026,95.559576 c 1.2,1.3 130.51331,220.842234 129.732,221.014164 -5.82653,1.28211 -47.96937,-0.55823 -56.05087,-1.10435 l -36.9064,-64.55997 c -8.19119,-8.28426 -26.4237,-49.37901 -32.41705,-52.6861 -1,0.6 -13.60768,20.9355 -31.80768,52.5355 L 126.6359,316.0232 63.999824,314.74948 C 79.729353,278.46241 190.35477,94.73696 191.1026,95.559576 Z M 162.69143,54.054413 c -1,-2.7 2.51496,-6.782001 5.21496,-7.782001 4.6,-1.8 8.77208,-1.083268 14.39708,11.791692 l 11.53013,19.962194 c 0,0 -7.55756,16.691804 -9.15756,13.991804"
     style="fill:#228b22;fill-opacity:1;stroke-width:5;stroke-dasharray:none"
     id="path1"
     sodipodi:nodetypes="ccsssccscssccscccccccccccc" />
  <path
     d="m 332.77221,320.05823 5.40664,-0.0773 c 11.71407,-0.16739 12.42751,0.6664 12.42751,12.3664 0,11.4 9.96964,19.20666 -8.22118,18.62875 l -5.36362,-0.1704 c 4.43106,-9.88857 4.32438,-22.63657 -4.24935,-30.74749 z m -19.65182,-4.3283 C 312.10948,310.9863 188.27253,100.27425 186.27253,97.474248 c -20.44957,31.798292 13.95885,-24.737481 19.60891,-34.476861 8.85687,-15.267152 10.94997,-17.374978 14.84997,-17.374978 1.9,0 3.45001,2.899965 4.45001,3.799965 5.52995,3.6 4.64997,4.450009 -7.35003,25.450009 -8.1,14.3 -12.79416,20.896048 -12.49416,22.396048 0.2,1.2 27.26918,46.553989 59.46918,102.353989 32.2,55.8 66.23934,117.69773 65.63269,116.02443"
     style="fill:#226422;fill-opacity:1;stroke-width:5;stroke-dasharray:none"
     id="path2"
     sodipodi:nodetypes="cssscccccscccscc" />
  <path
     style="fill:none;fill-opacity:1;stroke:#000000;stroke-width:7;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:none;stroke-opacity:1"
     d="M 182.63914,353.36816 H 45.401854 c -5.583865,0 -8.997798,-36.48772 1.357024,-36.48772 40.568006,0 228.548372,1.08677 300.472442,1.08677 4.89787,0 8.80162,35.63621 -0.26477,35.80675 -21.46257,0.40372 -138.07621,0.58562 -138.07621,0.58562"
     id="path3"
     sodipodi:nodetypes="cssssc" />
  <path
     style="fill:none;fill-opacity:1;stroke:#000000;stroke-width:7;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:none;stroke-opacity:1"
     d="m 60.83747,315.76962 c 0,0 118.71826,-208.42715 151.51762,-265.237264 5.93048,-10.271872 22.5811,-1.864308 16.1866,9.512737 L 207.67641,97.168433 334.3984,316.65736"
     id="path4"
     sodipodi:nodetypes="csscc" />
  <path
     style="fill:#ffffff;fill-opacity:1;stroke:#000000;stroke-width:7;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:none;stroke-opacity:1"
     d="m 195.99339,198.22571 -70.56009,118.89086 142.29451,0.65068 z"
     id="path8"
     sodipodi:nodetypes="cccc" />
  <path
     style="fill:none;fill-opacity:1;stroke:#000000;stroke-width:7;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:none;stroke-opacity:1"
     d="m 184.10793,96.521703 c 0,0 -8.15605,-13.696573 -21.68851,-37.135483 -7.76133,-13.443019 8.53042,-23.354494 16.68783,-9.225438 11.1469,19.306994 16.60326,27.711627 16.60326,27.711627"
     id="path5"
     sodipodi:nodetypes="cssc" />
  <path
     style="fill:#aaaaaa;fill-opacity:1;stroke:none;stroke-width:7;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:none;stroke-opacity:1"
     d="m 196.06457,204.92395 65.34548,109.43861 h -17.6128 l -55.51501,-96.29072 z"
     id="path9"
     sodipodi:nodetypes="ccccc" />
</svg>
<svg
  id="gite"
  width="${sheetIconSize}"
  height="${sheetIconSize}"
   viewBox="0 0 655.82062 622.15002"
   sodipodi:docname="home.svg"
   inkscape:version="1.4.2 (ebf0e940, 2025-05-08)"
   xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape"
   xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd"
   xmlns="http://www.w3.org/2000/svg"
   xmlns:svg="http://www.w3.org/2000/svg">
  <sodipodi:namedview
     id="namedview1"
     pagecolor="#32d47a"
     bordercolor="#000000"
     borderopacity="0.25"
     inkscape:showpageshadow="2"
     inkscape:pageopacity="0.0"
     inkscape:pagecheckerboard="0"
     inkscape:deskcolor="#d1d1d1"
     inkscape:zoom="0.66554339"
     inkscape:cx="213.35949"
     inkscape:cy="473.29746"
     inkscape:window-width="1680"
     inkscape:window-height="997"
     inkscape:window-x="0"
     inkscape:window-y="25"
     inkscape:window-maximized="0"
     inkscape:current-layer="g1" />
  <defs
     id="defs1" />
  <g
     id="g1"
     transform="translate(75.72989,55.23558)">
    <path
       style="fill:none;fill-opacity:1;stroke:#ffffff;stroke-width:150;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:none"
       d="M -0.73201554,273.0418 248.88528,19.76442 h 11.71225 L 505.09072,268.6497 v 8.05217 l -26.35256,30.74466 -7.32015,0.73201 -27.81659,-24.88853 -2.19605,190.32404 -5.12411,9.51621 -10.98023,8.78418 -339.655212,-0.73201 -8.052171,-7.32016 -5.124109,-8.78419 -2.196046,-187.39597 -28.548606,21.96046 -8.784187,-1.46403 -29.2806213,-28.5486 z"
       id="path12" />
    <path
       style="fill:#228b22;fill-opacity:1"
       d="M 90.920447,498.88639 C 84.220664,497.75243 78.867422,494.65537 73.729462,488.94072 65.800214,480.12149 66,481.95117 66,418.15285 c 0,-34.18567 0.37869,-57.09519 0.961783,-58.18471 0.528981,-0.98841 2.328981,-2.36342 4,-3.05558 L 74,355.65409 v -9.37868 c 0,-8.41353 -0.18788,-9.37868 -1.825686,-9.37868 -1.004127,0 -2.804127,-1.0415 -4,-2.31445 C 66.096055,332.37007 66,331.41027 66,312.8561 v -19.41172 l -9.25,9.58509 c -7.960764,8.24915 -9.91333,9.74647 -14.009256,10.74291 -8.762947,2.13181 -12.981143,0.0308 -26.622534,-13.2604 C 2.8520587,287.58642 1.1289097e-7,283.27588 1.1289097e-7,276.15129 1.1289097e-7,273.20666 0.6590156,269.52301 1.464479,267.96542 2.2699424,266.40783 55.795542,210.06767 120.41026,142.76508 250.87099,6.8773741 242.69805,14.602617 256,14.602617 c 13.29596,0 5.13442,-7.7135411 135.57026,128.128603 64.60617,67.28397 118.13266,123.62412 118.94775,125.20034 0.8151,1.57622 1.48199,5.2751 1.48199,8.21973 0,7.12459 -2.85206,11.43513 -16.11821,24.36069 -13.64506,13.29475 -17.86573,15.39602 -26.62253,13.25408 -4.09594,-1.00187 -6.04799,-2.50247 -14.00582,-10.76673 l -9.24656,-9.6026 -0.003,90.57186 c -0.004,102.50587 0.50093,95.93263 -8.08943,105.33185 -6.44463,7.05146 -12.17974,9.4791 -24.16125,10.22734 -10.7075,0.66868 -14.07104,-0.26972 -16.54408,-4.61569 -1.70028,-2.98796 -1.7529,-3.00271 -10.70868,-3.00271 -8.95578,0 -9.0084,0.0147 -10.70868,3.00271 -0.93977,1.6515 -2.88157,3.44865 -4.3151,3.99368 -3.12022,1.18631 -273.542547,1.16763 -280.555773,-0.0194 z"
       id="path11" />
    <path
       style="fill:#226422;fill-opacity:1"
       d="m 431.04797,277.6654 c -3.1093,-3.22623 -9.37371,-9.63433 -9.37371,-9.63433 -5.15553,-5.29889 -43.26223,-44.95934 -84.68155,-88.13434 -41.41931,-43.175 -76.58693,-79.14414 -78.15027,-79.931422 -2.46609,-1.241902 -3.21951,-1.241902 -5.69035,0 -1.56635,0.787282 -31.15235,30.906422 -65.74665,66.931422 -34.59431,36.025 -72.67271,75.625 -84.61868,88 l -21.719936,22.5 C 73.21826,299.89198 72.58814,295.21215 66,293.43623 l -9.25,9.12024 c -5.0875,5.01613 -10.232916,9.61974 -11.434259,10.23025 -3.019652,1.53455 -10.728126,1.39023 -14.59786,-0.2733 C 26.698718,310.78566 2.5004817,286.3433 1.0611073,282.55745 -0.57376598,278.25741 -0.20192911,272.20157 1.9483916,268.10689 3.0200069,266.0663 56.682507,209.49673 121.19839,142.39673 250.93457,7.4641135 243.08967,14.887415 255.92931,14.906504 c 10.76407,0.016 14.17979,2.233777 32.57069,21.147619 62.67789,64.460137 220.28323,229.470467 221.68851,232.104407 1.96914,3.69082 2.313,10.28894 0.75038,14.39892 -1.43937,3.78585 -25.63761,28.22821 -29.65677,29.95597 -4.16884,1.79211 -11.65014,1.77 -15.08178,-0.0446 -1.48519,-0.7853 -6.52534,-5.29545 -11.20034,-10.02253 0,0 -5.69208,-5.70488 -8.5,-8.59469 -5.19812,-5.3497 -10.27581,-10.8153 -15.45203,-16.1862 z"
       id="path10" />
    <path
       style="fill:#ffffff;fill-opacity:1;stroke:none"
       d="m 190.97107,483.95246 0.3822,-47.77787 c 0.42825,-53.53401 -0.11903,-51.38415 7.48051,-66.90805 3.33714,-6.81691 4.94659,-9.73519 11.82685,-16.58734 13.84235,-13.78579 25.89886,-18.54137 45.42941,-18.57147 19.38065,-0.0299 31.14434,4.64029 45.24933,18.61541 6.90914,6.84553 8.93231,8.32806 12.28346,15.17359 7.59911,15.52303 7.50887,14.74706 7.93698,68.25 l 0.38207,47.75 c 0,0 -76.98242,9.25538 -93.02587,9.25692 -32.41215,-6.4842 -37.94494,-9.20119 -37.94494,-9.20119 z"
       id="path9" />
    <path
       style="fill:#020303"
       d="M 87.499145,497.5089 C 78.841028,494.79507 72.543991,488.8557 68.296271,479.39673 66.635303,475.69803 66.5,471.07779 66.5,418.05869 v -57.33804 l 2.360784,-1.91196 c 3.209912,-2.59966 6.740215,-2.40187 9.684671,0.54259 L 81,361.80582 v 55.08529 c 0,52.82195 0.0809,55.24387 1.968914,58.94469 1.082903,2.12266 3.498518,4.80473 5.368034,5.96016 3.330212,2.05818 4.374864,2.10077 51.531082,2.10077 H 188 v -46.84004 c 0,-43.25506 0.15008,-47.42683 1.96086,-54.50692 7.79175,-30.46547 34.85415,-51.61831 66.03914,-51.61831 31.08581,0 58.26903,21.23746 66.03152,51.58851 1.82041,7.11774 1.96848,11.22 1.96848,54.53672 v 46.84004 h 23.36514 c 22.12369,0 23.50514,0.11012 26,2.07258 3.64388,2.86628 3.64388,7.98856 0,10.85484 -2.63486,2.07258 -2.63486,2.07258 -142.25,2.00568 C 122.13028,498.77761 90.622249,498.48781 87.499145,497.5089 Z M 309,437.07823 c 0,-39.92411 -0.23493,-47.61833 -1.59538,-52.25 -5.43656,-18.50888 -20.5133,-33.1525 -38.49769,-37.39172 -28.32299,-6.6762 -56.11362,9.48169 -64.31155,37.39172 C 203.23493,389.4599 203,397.15412 203,437.07823 v 46.8185 h 53 53 z m 90.75,59.84359 c -3.75916,-2.96666 -3.81254,-8.04414 -0.11514,-10.95251 2.22217,-1.74796 4.04403,-2.07258 11.63196,-2.07258 10.58319,0 14.57627,-1.81195 17.76427,-8.06093 C 430.94975,472.0749 431,469.49025 431,374.5543 v -97.4221 l -85.60537,-89.11773 c -83.86788,-87.30896 -85.68201,-89.117735 -89.38137,-89.117735 -3.69937,0 -5.51339,1.808715 -89.39463,89.133085 L 81,277.16291 v 26.93265 c 0,17.07189 -0.388388,27.65836 -1.060802,28.91478 -2.480209,4.63431 -10.570708,4.18531 -12.801362,-0.71044 C 66.417679,330.71933 66,322.97492 66,311.20269 v -18.59994 l -8.233925,8.80061 C 49.124915,310.63924 44.022709,313.85281 38,313.85281 c -6.294025,0 -11.092112,-3.27368 -23.663406,-16.14525 C 4.567423,287.70504 1.9457466,284.40896 1.0177234,280.96255 -0.41169882,275.65408 0.4486494,270.09699 3.3169312,266.1117 4.5176191,264.44342 58.079929,208.39219 122.34429,141.5534 c 91.96217,-95.646161 117.83572,-122.010614 121.5,-123.805212 6.76485,-3.313126 17.85183,-3.267046 24.45238,0.101629 3.7522,1.914984 30.31745,29.005042 122,124.410113 64.46183,67.07915 117.96565,123.099 118.89739,124.48857 2.41859,3.60706 3.12736,9.24085 1.78822,14.21405 -0.92803,3.44641 -3.5497,6.74249 -13.31887,16.74501 -12.5713,12.87157 -17.36939,16.14525 -23.66341,16.14525 -6.05664,0 -11.18915,-3.22709 -19.55533,-12.2955 l -7.94467,-8.61152 -0.5,91.22547 -0.5,91.22547 -2.6107,5.56658 c -3.09476,6.59868 -8.38236,12.01873 -14.76202,15.13176 -3.67995,1.79569 -6.77691,2.3336 -15.12728,2.62748 -9.57404,0.33694 -10.74251,0.17814 -13.25,-1.80073 z M 140.47538,193.86786 C 195.96046,136.10199 242.96445,87.839491 244.92867,86.617868 c 5.14695,-3.201068 17.02413,-3.183304 22.19637,0.0332 1.99378,1.239882 49.00292,49.502372 104.46477,107.249992 55.46185,57.74762 101.53825,104.99567 102.39201,104.99567 2.22747,0 23.29127,-21.40837 22.86735,-23.24141 -0.57332,-2.479 -233.65196,-244.472495 -236.09757,-245.127881 -3.51888,-0.943005 -7.42254,-0.737758 -10.22124,0.537415 -2.93674,1.33807 -234.847991,242.345326 -235.379528,244.611566 -0.426361,1.81782 20.661377,23.22031 22.878823,23.22031 0.86007,0 46.960648,-47.26299 102.445725,-105.02887 z"
       id="path8" />
  </g>
</svg>
</body></html>
  `;
const sheet = new protomapsL.Sheet(ICONS);

const campingLabelSymbolizer = new protomapsL.OffsetTextSymbolizer({
  label_props: ["name"],
  fill: (z, f) => (z < showLabelFromZoom ? "transparent" : "forestgreen"),
  stroke: "white",
  width: (z, f) => (z < showLabelFromZoom ? 0 : 2),
  maxLineChars: 15,
  lineHeight: 1.2,
  placements: [protomapsL.TextPlacements.S],
  offsetY: 1,
  justify: 2,
  font: (z, f) => {
    return "700 14px sans-serif";
  },
});
const campingLabelRules = [
  {
    dataLayer: "camping",
    filter: (z, f) => f.props.category === "Camping",
    symbolizer: new protomapsL.GroupSymbolizer([
      new protomapsL.IconSymbolizer({
        name: "camping", // matches the SVG id
        sheet: sheet,
      }),
      campingLabelSymbolizer,
    ]),
  },
];
const campingLayer = protomapsL.leafletLayer({
  url: "/bdd/datatourisme/camping_2.pmtiles",
  tasks: [sheet.load()],
  labelRules: campingLabelRules,
  minZoom: 12,
  maxDataZoom: 14,
  pane: "overlayPane",
  attribution: "Datatourisme",
  devicePixelRatio: 2,
});

const gitesLabelRules = [
  {
    dataLayer: "camping",
    filter: (z, f) => f.props.category === "Gite",
    symbolizer: new protomapsL.GroupSymbolizer([
      new protomapsL.IconSymbolizer({
        name: "gite", // matches the SVG id
        sheet: sheet,
      }),
      campingLabelSymbolizer,
    ]),
  },
];

const giteLayer = protomapsL.leafletLayer({
  url: "/bdd/datatourisme/camping_2.pmtiles",
  tasks: [sheet.load()],
  labelRules: gitesLabelRules,
  minZoom: 12,
  maxDataZoom: 14,
  pane: "overlayPane",
  attribution: "Datatourisme",
  devicePixelRatio: 2,
});

//  --- Ajout des zones de biodiv ---
const biodivPaintRules = [
  {
    dataLayer: "biodiv",
    filter: (z, f) => {
      const rules = f.props.rules ? JSON.parse(f.props.rules) : [];
      return (
        f.props.practices.includes(2) &&
        (rules.some((rule) => rule.code === "CLIMBING-REGULATED") ||
          rules.length === 0)
      );
    },
    symbolizer: new protomapsL.PolygonSymbolizer({
      fill: "tomato",
      opacity: 0.6,
    }),
  },
  {
    filter: (z, f) => {
      return (
        f.props.practices.includes(2) &&
        JSON.parse(f.props.rules)?.some(
          (rule) => rule.code === "CLIMBING-FORBIDDEN"
        )
      );
    },
    dataLayer: "biodiv",
    symbolizer: new protomapsL.PolygonSymbolizer({
      fill: "darkred",
      opacity: 0.7,
    }),
  },
];
const biodivLabelRules = [
  {
    dataLayer: "biodiv",
    filter: (z, f) => z >= 14 && f.props.name && f.props.name.length > 0,
    symbolizer: new protomapsL.OffsetTextSymbolizer({
      label_props: (z, f) => f.props.name.fr,
      fill: "tomato",
      stroke: "white",
      width: 2,
      maxLineChars: 15,
      lineHeight: 1.2,
      placements: [protomapsL.TextPlacements.CENTER],
      justify: 1,
      font: (z, f) => {
        return "700 14px sans-serif";
      },
    }),
  },
];
const biodivLayer = protomapsL.leafletLayer({
  url: "/bdd/biodiv/biodiv.pmtiles",
  paintRules: biodivPaintRules,
  labelRules: biodivLabelRules,
  // minZoom: 12,
  maxDataZoom: 14,
  pane: "overlayPane",
  attribution: "BiodivSport",
});

export { campingLayer, giteLayer, trainlinesLayer, tgvLayer, biodivLayer };
