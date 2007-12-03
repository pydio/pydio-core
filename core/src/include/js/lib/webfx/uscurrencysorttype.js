// Thanks to Brian K. Cantwell for the initial code

function usCurrencyConverter( s )
{
	var n = s;
	var i = s.indexOf( "$" );
	if ( i == -1 )
		i = s.indexOf( "," );
	if ( i != -1 )
	{
		var p1 = s.substr( 0, i );
		var p2 = s.substr( i + 1, s.length );
		return usCurrencyConverter( p1 + p2 );
	}

	return parseFloat( n );
}

SortableTable.prototype.addSortType( "UsCurrency", usCurrencyConverter );
