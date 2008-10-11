AjxpSortable = Class.create(SortableTable, {

	initialize: function($super, oTable, oSortTypes, oTHead) {
		$super(oTable, oSortTypes, oTHead);
		this.addSortType( "NumberK", this.replace8a8 );
		this.addSortType( "NumberKo", this.replace8oa8 );
		this.addSortType( "MyDate", this.replaceDate );
		this.addSortType( "StringDirFile", this.toUpperCase, false, this.splitDirsAndFiles.bind(this) );		
	},

	replace8a8: function(str) {
		str = str.toUpperCase();
		var splitstr = "____";
		var ar = str.replace(
			/(([0-9]*\.)?[0-9]+([eE][-+]?[0-9]+)?)(.*)/,
		 "$1"+splitstr+"$4").split(splitstr);
		var num = Number(ar[0]).valueOf();
		if(ar[1]){
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
		}	
		return num;
	},
	
	replace8oa8: function(str) {
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
		if(ar[1]){
			var ml = ar[1].replace(/\s*(KO|MO|GO|T|KB|MB|GB)\s*/, "$1");
		
			if (ml == "KO" || ml == "KB")
				num *= 1024;
			else if(ml == "MO" || ml == "MB")
				num *= 1024 * 1024;
			else if (ml == "GO" || ml == "GB")
				num *= 1024 * 1024 * 1024;
			else if (ml == "T")
				num *= 1024 * 1024 * 1024 * 1024;
			// B and no prefix
		}
		return num;
	},
	
	replaceDate: function(s) {
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
	},
	
	splitDirsAndFiles: function(oRow, nColumn) {
		var s;
		var c = oRow.cells[nColumn];
		if (typeof c.innerText != "undefined")
			s = c.innerText;
		else
			s = this.getInnerText(c);
		if(s[0] == ' ') s = s.substr(1, (s.length-1));	
		if(oRow.getAttribute('is_file') == '0'){		
			s = '000'+s;
		}
		return s.toUpperCase();
	}	
	
});