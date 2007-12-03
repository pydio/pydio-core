// Thanks to Bernhard Wagner for submitting this function

function replace8a8(str) {
	str = str.toUpperCase();
	var splitstr = "____";
	var ar = str.replace(
		/(([0-9]*\.)?[0-9]+([eE][-+]?[0-9]+)?)(.*)/,
	 "$1"+splitstr+"$4").split(splitstr);
	var num = Number(ar[0]).valueOf();
	var ml = ar[1].replace(/\s*([KMGB])\s*/, "$1");

	if (ml == "K")
		num *= 1024;
	else if(ml == "M")
		num *= 1024 * 1024;
	else if (ml == "G")
		num *= 1024 * 1024 * 1024;
	else if (ml == "T")
		num *= 1024 * 1024 * 1024 * 1024;
	// B and no prefix

	return num;
}

function replace8oa8(str) {
	str = str.toUpperCase();
	if(str == "-")
	{
		return 0;
	}
	var splitstr = "____";
	var ar = str.replace(
		/(([0-9]*\.)?[0-9]+([eE][-+]?[0-9]+)?)(.*)/,
	 "$1"+splitstr+"$4").split(splitstr);
	var num = Number(ar[0]).valueOf();
	var ml = ar[1].replace(/\s*(KO|MO|GO|B)\s*/, "$1");

	if (ml == "KO")
		num *= 1024;
	else if(ml == "MO")
		num *= 1024 * 1024;
	else if (ml == "GO")
		num *= 1024 * 1024 * 1024;
	else if (ml == "T")
		num *= 1024 * 1024 * 1024 * 1024;
	// B and no prefix

	return num;
}

function replaceDate (s) {
	var parts1 = s.split(" ");
	
	var parts = parts1[0].split("/");
	var d = new Date(0);
	d.setFullYear(parts[2]);
	d.setDate(parts[0]);
	d.setMonth(parts[1] - 1);
	
	var hours = parts1[1].split(":");
	d.setHours(hours[0]);
	d.setMinutes(hours[1]);	
	return d.getTime();
};

function splitDirsAndFiles(oRow, nColumn) {
	var s;
	var c = oRow.cells[nColumn];
	if (typeof c.innerText != "undefined")
		s = c.innerText;
	else
		s = SortableTable.getInnerText(c);
	if(s[0] == ' ') s = s.substr(1, (s.length-1));	
	if(oRow.getAttribute('is_file') == 'non'){		
		s = '000'+s;
	}
	return s.toUpperCase();
}

SortableTable.prototype.addSortType( "NumberK", replace8a8 );
SortableTable.prototype.addSortType( "NumberKo", replace8oa8 );
SortableTable.prototype.addSortType( "MyDate", replaceDate );
SortableTable.prototype.addSortType( "StringDirFile", SortableTable.toUpperCase, false, splitDirsAndFiles );