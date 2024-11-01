<?php
/*
* class-wic-db-us-address-words-object.php
*	supports retrieval of lists in format like $wpdb output
*
*/

class WIC_DB_US_Address_Words_Object {

	 public static function get_terms ( $term_type) {
	
		switch ( $term_type ) {
		
		// state and territory abbreviations from http://pe.usps.gov/text/pub28/28apb.htm
		case 'states':
			return (
			  'Alabama|Alaska|American Samoa|Arizona|Arkansas|California|Colorado|Connecticut'
			. '|Delaware|District of Columbia|Federated States of Micronesia|Florida|Georgia|'
			. 'Guam|Hawaii|Idaho|Illinois|Indiana|Iowa|Kansas|Kentucky|Louisiana|Maine|Marshall Islands'
			. '|Maryland|Massachusetts|Michigan|Minnesota|Mississippi|Missouri|Montana|Nebraska|Nevada|'
			. 'New Hampshire|New Jersey|New Mexico|New York|North Carolina|North Dakota|Northern Mariana'
			. ' Islands|Ohio|Oklahoma|Oregon|Palau|Pennsylvania|Puerto Rico|Rhode Island|South '
			. 'Carolina|South Dakota|Tennessee|Texas|Utah|Vermont|Virgin Islands|Virginia|Washington|'
			. 'West Virginia|Wisconsin|Wyoming|AL|AK|AS|AZ|AR|CA|CO|CT|DE|DC|FM|FL|GA|GU|HI|ID|IL|IN|'
			. 'IA|KS|KY|LA|ME|MH|MD|MA|MI|MN|MS|MO|MT|NE|NV|NH|NJ|NM|NY|NC|ND|MP|OH|OK|OR|PW|PA|PR|RI|'
			. 'SC|SD|TN|TX|UT|VT|VI|VA|WA|WV|WI|WY');

		// street abbreviations from http://pe.usps.gov/text/pub28/28apc_002.htm
		case 'streets':
			return( 'ALLEE|ALLEY|ALLY|ALY|ANEX|ANNEX|ANNX|ANX|ARC|ARCADE|AV|AVE|AVEN|AVENU|AVENUE|'
			. 'AVN|AVNUE|BAYOO|BAYOU|BCH|BEACH|BEND|BG|BGS|BLF|BLFS|BLUF|BLUFF|BLUFFS|BLVD|BND|'
			. 'BOT|BOTTM|BOTTOM|BOUL|BOULEVARD|BOULV|BR|BRANCH|BRDGE|BRG|BRIDGE|BRK|BRKS|'
			. 'BRNCH|BROOK|BROOKS|BTM|BURG|BURGS|BYP|BYPA|BYPAS|BYPASS|BYPS|BYU|CAMP|CANYN|'
			. 'CANYON|CAPE|CAUSEWAY|CAUSWA|CEN|CENT|CENTER|CENTERS|CENTR|CENTRE|CIR|CIRC|CIRCL'
			. '|CIRCLE|CIRCLES|CIRS|CLB|CLF|CLFS|CLIFF|CLIFFS|CLUB|CMN|CMNS|CMP|CNTER|CNTR|CNYN|'
			. 'COMMON|COMMONS|COR|CORNER|CORNERS|CORS|COURSE|COURT|COURTS|COVE|COVES|CP|CPE|'
			. 'CRCL|CRCLE|CREEK|CRES|CRESCENT|CREST|CRK|CROSSING|CROSSROAD|CROSSROADS|CRSE|'
			. 'CRSENT|CRSNT|CRSSNG|CRST|CSWY|CT|CTR|CTRS|CTS|CURV|CURVE|CV|CVS|CYN|DALE|DAM|'
			. 'DIV|DIVIDE|DL|DM|DR|DRIV|DRIVE|DRIVES|DRS|DRV|DV|DVD|EST|ESTATE|ESTATES|ESTS|EXP'
			. '|EXPR|EXPRESS|EXPRESSWAY|EXPW|EXPY|EXT|EXTENSION|EXTENSIONS|EXTN|EXTNSN|EXTS|FALL|'
			. 'FALLS|FERRY|FIELD|FIELDS|FLAT|FLATS|FLD|FLDS|FLS|FLT|FLTS|FORD|FORDS|FOREST|FORESTS|'
			. 'FORG|FORGE|FORGES|FORK|FORKS|FORT|FRD|FRDS|FREEWAY|FREEWY|FRG|FRGS|FRK|FRKS|FRRY|FRST|'
			. 'FRT|FRWAY|FRWY|FRY|FT|FWY|GARDEN|GARDENS|GARDN|GATEWAY|GATEWY|GATWAY|GDN|GDNS|GLEN|GLENS|'
			. 'GLN|GLNS|GRDEN|GRDN|GRDNS|GREEN|GREENS|GRN|GRNS|GROV|GROVE|GROVES|GRV|GRVS|GTWAY|'
			. 'GTWY|HARB|HARBOR|HARBORS|HARBR|HAVEN|HBR|HBRS|HEIGHTS|HIGHWAY|HIGHWY|HILL|HILLS|HIWAY'
			. '|HIWY|HL|HLLW|HLS|HOLLOW|HOLLOWS|HOLW|HOLWS|HRBOR|HT|HTS|HVN|HWAY|HWY|INLET|INLT|IS|'
			. 'ISLAND|ISLANDS|ISLE|ISLES|ISLND|ISLNDS|ISS|JCT|JCTION|JCTN|JCTNS|JCTS|JUNCTION|JUNCTIONS'
			. '|JUNCTN|JUNCTON|KEY|KEYS|KNL|KNLS|KNOL|KNOLL|KNOLLS|KY|KYS|LAKE|LAKES|LAND|LANDING|LANE|'
			. 'LCK|LCKS|LDG|LDGE|LF|LGT|LGTS|LIGHT|LIGHTS|LK|LKS|LN|LNDG|LNDNG|LOAF|LOCK|LOCKS|LODG|LODGE'
			. '|LOOP|LOOPS|MALL|MANOR|MANORS|MDW|MDWS|MEADOW|MEADOWS|MEDOWS|MEWS|MILL|MILLS|MISSION|MISSN|'
			. 'ML|MLS|MNR|MNRS|MNT|MNTAIN|MNTN|MNTNS|MOTORWAY|MOUNT|MOUNTAIN|MOUNTAINS|MOUNTIN|MSN|'
			. 'MSSN|MT|MTIN|MTN|MTNS|MTWY|NCK|NECK|OPAS|ORCH|ORCHARD|ORCHRD|OVAL|OVERPASS|OVL|PARK|PARKS'
			. '|PARKWAY|PARKWAYS|PARKWY|PASS|PASSAGE|PATH|PATHS|PIKE|PIKES|PINE|PINES|PKWAY|PKWY|PKWYS|PKY'
			. '|PL|PLACE|PLAIN|PLAINS|PLAZA|PLN|PLNS|PLZ|PLZA|PNE|PNES|POINT|POINTS|PORT|PORTS|PR|PRAIRIE|'
			. 'PRK|PRR|PRT|PRTS|PSGE|PT|PTS|RAD|RADIAL|RADIEL|RADL|RAMP|RANCH|RANCHES|RAPID|RAPIDS|RD|RDG|'
			. 'RDGE|RDGS|RDS|REST|RIDGE|RIDGES|RIV|RIVER|RIVR|RNCH|RNCHS|ROAD|ROADS|ROUTE|ROW|RPD|RPDS|RST|'
			. 'RTE|RUE|RUN|RVR|SHL|SHLS|SHOAL|SHOALS|SHOAR|SHOARS|SHORE|SHORES|SHR|SHRS|SKWY|SKYWAY|SMT|'
			. 'SPG|SPGS|SPNG|SPNGS|SPRING|SPRINGS|SPRNG|SPRNGS|SPUR|SPURS|SQ|SQR|SQRE|SQRS|SQS|SQU|SQUARE|'
			. 'SQUARES|ST|STA|STATION|STATN|STN|STR|STRA|STRAV|STRAVEN|STRAVENUE|STRAVN|STREAM|STREET|STREETS'
			. '|STREME|STRM|STRT|STERET|STRVN|STRVNUE|STS|SUMIT|SUMITT|SUMMIT|TER|TERR|TERRACE|THROUGHWAY|TPKE|TRACE|'
			. 'TRACES|TRACK|TRACKS|TRAFFICWAY|TRAIL|TRAILER|TRAILS|TRAK|TRCE|TRFY|TRK|TRKS|TRL'
			. '|TRLR|TRLRS|TRLS|TRNPK|TRWY|TUNEL|TUNL|TUNLS|TUNNEL|TUNNELS|TUNNL|TURNPIKE|TURNPK|'
			. 'UN|UNDERPASS|UNION|UNIONS|UNS|UPAS|VALLEY|VALLEYS|VALLY|VDCT|VIA|VIADCT|VIADUCT|VIEW|'
			. 'VIEWS|VILL|VILLAG|VILLAGE|VILLAGES|VILLE|VILLG|VILLIAGE|VIS|VIST|VISTA|VL|VLG|VLGS|VLLY'
			. 'VLY|VLYS|VST|VSTA|VW|VWS|WALK|WALKS|WALL|WAY|WAYS|WELL|WELLS|WL|WLS|WY|XING|XRD|XRDS');
		
		// secondary unit designators from http://pe.usps.gov/text/pub28/28apc_003.htm#ep538629
		case 'apartments':
			return( 'Apartment|APT|Basement|BLDG|BSMT|Building|Department|DEPT|FL|Floor|'
			. 'FRNT|Front|Hanger|HNGR|Key|KEY|LBBY|Lobby|Lot|LOT|Lower|LOWR|OFC|Office'
			. '|Penthouse|PH|Pier|PIER|Rear|REAR|RM|Room|Side|SIDE|Slip|SLIP|Space|SPC'
			. '|STE|Stop|STOP|Suite|Trailer|TRLR|Unit|UNIT|Upper|UPPR');
			
		case 'special_streets':
			return( 'FENWAY|BROADWAY|RIVERWAY' );
		
		// asssorted title and suffixes that will discard from names 
		// https://en.wikipedia.org/wiki/Suffix_%28name%29
		// note that treating M as a salutation means will err if it is true first initial
		case 'titles': 
			return( 'Mr|Ms|Miss|Mrs|Jr|Sr|II|III|IV|Esq|A.B|B.A|B.S|B.E|B.F.A|B.Tech|' 
			. 'L.L.B|B.Sc|M.A|M.S|M.F.A|LL.M|M.L.A|M.B.A|M.Sc|M.Eng|J.D|M.D|D.O|'
			. 'Pharm.D|Ph.D|Ed.D|D.Phil|LL.D|Eng.D|Dr|CA|CPA|C.P.A.|Accountant|P.E|AB|BA|BS|BE|BFA|'
			. 'BTech|LLB|BSc|MA|MS|MFA|LLM|MLA|MBA|MSc|MEng|JD|MD|DO|PharmD|PhD|EdD|'
			. 'DPhil|LLD|EngD|Dr|Atty|Attorney|Lawyer|CA|PE|The Honorable|Honorable|Hon|State Senator|Sen|Senator|Rep|' 
			. 'Representative|State Representative|Councilor|On behalf of|M|(SEN)|(HOU))|President|Exeutive Director|RN|R.N.' );

		case 'pre_titles': 
			return( 'Mr|Ms|Miss|M|Mrs|Dr|Atty|The Honorable|Honorable|Hon|State Senator|Senator|Sen|' 
			. 'State Representative|Representative|Rep|Councilor|On behalf of|Governor|Judge|' 
			. 'Father|Monsignor|Brother|Sister|Rabbi|Imam|Doctor' );
		
		case 'post_titles': 
			return( 'Jr|Sr|II|III|IV|Esq|A.B|B.A|B.S|B.E|B.F.A|B.Tech|' 
			. 'L.L.B|B.Sc|M.A|M.S|M.F.A|LL.M|M.L.A|M.B.A|M.Sc|M.Eng|J.D|M.D|D.O|'
			. 'Pharm.D|Ph.D|Ed.D|D.Phil|LL.D|Eng.D|C.A|CPA|C.P.A.|Accountant|P.E|AB|BA|BS|BE|BFA|'
			. 'BTech|LLB|BSc|MA|MS|MFA|LLM|MLA|MBA|MSc|MEng|JD|MD|DO|Partner|PharmD|PhD|EdD|'
			. 'DPhil|LLD|EngD|CA|PE|(SEN)|(HOU)|President|Exeutive Director|RN|R.N.' );
		
		case 'closings':
			return 'Appreciatively|Best regards|Best wishes|Best|Cordially yours|'
			. 'Cordially|Faithfully|Fond regards|In appreciation|In sympathy|Kind regards|'
			. 'Kind thanks|Kind wishes|Kindest regards|Many thanks|Regards|Respectfully yours|'
			. 'Respectfully|Sincerely yours|Sincerely|Very Sincerely|Thank you for your assistance'
			. 'in this matter|Thank you for your consideration|Thank you for your recommendation|'
			. 'Thank you for your time|Thank you|Thanks|Thanks again|Thanks|Very Respectfully Yours|'
			. 'Warm regards|Warm wishes|Warmly|With anticipation|With appreciation|With deepest sympathy|'
			. 'With gratitude|With sincere thanks|With sympathy|Your help is greatly appreciated|Yours|'
			. 'Yours cordially|Yours faithfully|Yours respectfully|Yours sincerely|Yours truly';
			
		case 'non_names':
			return 'academy|accountants|active|actor|address|ads|adult|agency|airforce|analytics|apartments|'
			. 'app|architect|army|art|associates|association|auction|audible|author|auto|autos|baby|band|bank|'
			. 'banque|bar|bargains|baseball|basketball|beauty|beer|beknown|bet|bible|bid|bike|bingo|bio|blog|'
			. 'blue|boats|book|booking|bot|boutique|box|broadway|broker|build|builders|business|buy|buzz|by|bzh|'
			. 'cab|cafe|call us|call me|call now|camera|camp|capital|car|cards|care|career|careers|cars|cash|'
			. 'cashbackbonus|casino|catering|catholic|cell|center|charity|chat|cheap|church|city|cityeats|claims|' 
			. 'cleaning|clinic|clothing|club|co|coach|codes|coffee|college|contact|com|community|company|compare|'
			. 'computer|comsec|condos|construction|consulting|contact|contractors|cooking|cool|corp|corporation|'
			. 'country|coupon|coupons|courses|credit|creditcard|cricket|cruise|cruises|dad|dance|data|date|dating|'
			. 'deal|dealer|deals|delivery|dental|dentist|design|dev|diamonds|digital|direct|directory|discount|diy|'
			. 'docs|dog|download|drive|earth|eat|eco|ecom|education|email|energy|engineer|engineering|enterprises|'
			. 'equipment|estate|events|exchange|expert|exposed|express|fail|faith|family|fan|fans|farm|fashion|feedback|'
			. 'film|finance|financial|financialaid|fish|fishing|fit|fitness|flights|florist|food|football|forsale|for sale|'
			. 'forum|foundation|free|fun|fund|furniture|futbol|fyi|gallery|games|garden|ged|gent|gifts|gives|giving|glass|'
			.' global|golf|graphics|gratis|gripe|grocery|group|guide|guru|hair|headquarters|health|healthcare|here|hockey|'
			. 'holdings|holiday|home|horse|hospital|host|hotel|inc|incorporated|industries|ink|institute|insurance|insure|'
			. 'international|investments|jewelry|kinder|kitchen|land|lat|latino|lease|legal|lgbt|life|lifeinsurance|lifestyle|'
			. 'lighting|limited|limo|live|living|llc|llp|loan|loans|location|lotto|lp|ltd|luxe|luxury|mail|mailing|management|'
			. 'map|market|marketing|markets|media|medical|memorial|men|menu|mls|mobile|money|mortgage|motorcycles|mov|movie|'
			. 'movistar|music|mutual|mutualfunds|navy|net|network|new|news|office|one|onl|online|org|organic|other|our|partner|'
			. 'partners|partnership|parts|party|pay|permanent|pet|pets|pharmacy|phd|phone|photography|photos|physio|pictures|'
			. 'pink|pizza|plumbing|plus|poker|press|prime|Pro|productions|prof|promo|properties|Protection|pub|pw|racing|radio|'
			. 'reach|realestate|realtor|recipes|red|rehab|rent|rentals|repair|report|rest|restaurant|retirement|review|reviews|'
			. 'rich|rip|rocks|rodeo|room|rsvp|rugby|run|sale|salon|save|scholarships|school|science|search|seat|secure|security|'
			. 'services|sex|shoes|shop|shopping|show|silk|singles|site|ski|soccer|social|software|solar|solutions|song|soy|spa|'
			. 'space|sport|sports|spreadbetting|storage|store|stream|studio|study|style|sucks|supplies|supply|support|surf|surgery|'
			. 'systems|tax|taxi|team|tech|technology|tel|tennis|temporary|thai|theater|theatre|tickets|tips|tires|today|'
			. 'tools|top|tour|tours|town|toys|trade|trading|training|translations|travel|trust|us|tube|tunes|university|vacations|'
			. 'ventures|vet|video|villas|vip|vision|vodka|vote|voto|voyage|wales|wang|watch|watches|web|webcam|webs|website|'
			. 'wed|wedding|wiki|win|wine|work|works|world|wtf|xin|xyz|yoga|your|zip|zone';

		case 'disqualifiers':
			return 'statehouse|state house|city hall|town hall|capitol';
							
		default: return ( false );
		
		} // close switch
	}	
}