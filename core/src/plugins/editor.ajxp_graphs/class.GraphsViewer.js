/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */
Class.create("GraphsViewer", AbstractEditor, {


    queriesData: null,
    charts: null,
    defaultCount:31,
    defaultLinksUnits:"days",
    colors: [ '#77b8e2', '#e35d52', '#d7a76A', '#399C9B', '#156FAF', '#4ACEB0' ],
    colorIndex:1,

    initialize: function($super, oFormObject, editorOptions)
    {
        editorOptions = Object.extend({
            fullscreen:true
        }, editorOptions);
        $super(oFormObject, editorOptions);
        this.charts = $H();

    },

    destroy: function(){
        // TODO: Shall we destroy the SVG objects?
        this.charts = $H();
    },

    open : function($super, node){
        $super(node);
        this.node = node;
        if(this.node.getMetadata().get("graph_viewer_class")){
            this.element.addClassName(this.node.getMetadata().get("graph_viewer_class"));
        }
        if(this.node.getMetadata().get("graph_default_count")){
            this.defaultCount = parseInt(this.node.getMetadata().get("graph_default_count"));
        }
        if(this.node.getMetadata().get("graph_default_links_units")){
            this.defaultLinksUnits = this.node.getMetadata().get("graph_default_links_units");
        }
        this.loadQueries();
    },

    loadQueries : function(){
        var action = "get_action=analytic_list_queries";
        if(this.node.getMetadata().get("graph_load_all")){
            action = this.node.getMetadata().get("graph_load_all");
        }
        var conn = new Connexion();
        conn.setParameters($H(action.toQueryParams()));
        conn.onComplete = function(transport){
            this.parseAndLoadQueries(transport.responseJSON);
        }.bind(this);
        conn.sendAsync();
    },

    parseAndLoadQueries: function(queriesData){
        if(this.element.down("#graph_viewer_loader")){
            this.element.down("#graph_viewer_loader").remove();
        }
        this.element.down("#graph_viewer_container").update('');
        this.queriesData = $A(queriesData);
        this.queriesData.each(function(qData){
            var div;
            if(qData['SEPARATOR']){
                div = new Element('div', {class:'tabrow', style:'clear:left;'}).update('<li class="selected">' + qData['LABEL'] + '</li>');
                this.element.down("#graph_viewer_container").insert(div);
                if(qData['UPDATER']){
                    this.buildUpdaterButtons(div, qData['UPDATER']);
                }
                return;
            }
            div = new Element('div', {id:qData['NAME']+'_container'});
            this.element.down("#graph_viewer_container").insert(div);
            if(qData["FIGURE"]){
                div.addClassName("cumulated_figure");
                div.update('<div class="innerTitle">'+qData['LABEL']+'</div>' + '<div class="figure">&nbsp;</div>');
            }else{
                div.insert({top:('<div class="innerTitle">'+qData['LABEL']+'</div>')});
                div.insert(('<div style="text-align: center; padding:100px;">Loading...</div>'));
            }
            if(!qData["COUNT"]) qData["COUNT"] = this.defaultCount;
            this.loadData(qData['NAME'], null, 0, qData['COUNT']);
        }.bind(this));
    },

    buildUpdaterButtons:function(div, updaterData){
        $A(updaterData['buttons']).each(function(b){
            var button = new Element('a').update(b['label']).observe("click", function(e){
                var params = b['parameters'];
                var charts = updaterData['charts'];
                $A(charts).each(function(c){
                    this.loadData(c, this.charts.get(c), null, null, params);
                }.bind(this));
                div.select('a').invoke("removeClassName", "selected");
                button.addClassName("selected");
            }.bind(this));
            if(b['default']) button.addClassName("selected");
            div.insert(button);
        }.bind(this));

    },

    getQueryByName: function(queryName){
        return this.queriesData.detect(function(q){
            return q['NAME'] == queryName;
        });
    },

    loadData : function(queryName, chart, start, count, additionalParameters){
        var action = "get_action=analytic_query";
        if(this.node.getMetadata().get("graph_load_query")){
            action = this.node.getMetadata().get("graph_load_query");
        }
        var conn = new Connexion();
        if(!start) start = 0;
        if(!count) count = this.defaultCount;
        var params = action.toQueryParams();
        params = Object.extend(params, {
            query_name: queryName,
            start:start,
            count:count
        });
        if(additionalParameters){
            params = Object.extend(params, additionalParameters);
        }
        conn.setParameters($H(params));
        conn.onComplete = function(transport){
            if(chart){
                this.updateChart(chart, queryName, transport.responseJSON);
            }else{
                this.createChart(queryName, transport.responseJSON);
            }
        }.bind(this);
        conn.sendAsync();
    },

    createChart : function(queryName, jsonData){
        var qData = this.getQueryByName(queryName);
        var div = this.element.down("#"+queryName+'_container');
        div.update('');
        if(qData['AXIS']){
            var height = 300;
            var legendY = 280;
            if(qData["DIRECTION"] && qData["DIRECTION"] == "horizontal"){
                height = 600;
            }else if(qData["DIAGRAM"] && qData["DIAGRAM"] == "pie"){
                height = 320;
            }
            var svg = dimple.newSvg("#"+this.element.id+" #"+queryName+'_container', '100%', height);
            var chart = new dimple.chart(svg, jsonData['data']);
            var colorIndex = this.colorIndex % this.colors.length;
            chart.defaultColors[0] = new dimple.color(this.colors[colorIndex]);
            this.colorIndex ++;
            chart.setMargins(80, 20, 40, 80);
            if(qData["DIAGRAM"] && qData["DIAGRAM"] == "pie"){
                chart.addMeasureAxis("p", qData['AXIS']['y']);
                chart.setMargins(80, 30, 40, 80);
                legendY = 270;
            }else if(qData["DIRECTION"] && qData["DIRECTION"] == "horizontal"){
                chart.setMargins('40%', 20, 40, 80);
                chart.addMeasureAxis("x", qData['AXIS']['x']);
                chart.addCategoryAxis("y", qData['AXIS']['y']);
            }else{
                // Default vertical bars
                var x;
                if(qData["AXIS"]["sery"]){
                    x = chart.addCategoryAxis("x", [qData['AXIS']['x'], qData['AXIS']['sery']]);
                }else{
                    x = chart.addCategoryAxis("x", qData['AXIS']['x']);
                }
                chart.addMeasureAxis("y", qData['AXIS']['y']);
                if(qData['AXIS']['order']){
                    x.addOrderRule(qData['AXIS']['order']);
                }
            }

            if(qData["DIAGRAM"]){
                if(qData["DIAGRAM"] == "pie"){
                    var ring = chart.addSeries(qData['AXIS']['x'], dimple.plot[qData["DIAGRAM"]]);
                    if(qData["PIE_RING"]){
                        ring.innerRadius = qData["PIE_RING"];
                    }
                }else{
                    chart.addSeries(qData["AXIS"]["sery"], dimple.plot[qData["DIAGRAM"]]);
                }
            }else{
                var s = chart.addSeries(qData["AXIS"]["sery"], dimple.plot.line);
                s.interpolation = 'cardinal';
            }
            chart.addLegend("5%", legendY, "90%", 40, "center");
            chart.draw();
            if(qData['LEGEND']){
                var el= this.element.down('#'+queryName+'_container');
                el.down('.dimple-legend-text').innerHTML = qData['LEGEND'];
            }
            div.insert({top:('<table class="innerTitle"><tr><td>'+qData['LABEL']+'</td></tr></table>')});
            this.updateLinks(chart, queryName, jsonData);
            this.charts.set(queryName, chart);
        }else if(qData["FIGURE"]){
            div.addClassName("cumulated_figure");
            div.update('<div class="innerTitle">'+qData['LABEL']+'</div>' + '<div class="figure">' + jsonData['data'][0]['total'] + '</div>');
        }
        this.element.fire("editor:resize");
    },

    updateChart : function(chart, queryName, jsonData){
        chart.data = jsonData["data"];
        var qType = this.getQueryByName(queryName)['DIAGRAM'];
        if(Prototype.Browser.Gecko && !Prototype.Browser.IE10plus && (!qType || qType == 'bar' || qType == 'area')){
            chart.setMargins(80, 20, 40, 145);
        }
        chart.draw(500);
        if(jsonData['LEGEND']){
            var el= this.element.down('#'+queryName+'_container');
            el.down('.dimple-legend-text').innerHTML = jsonData['LEGEND'];
        }
        this.updateLinks(chart, queryName, jsonData);
    },

    updateLinks : function(chart, queryName, jsonData){

        var container = this.element.down('#' + queryName+'_container').down('tr');
        var linkCont = container.down('td.chart_links');
        if(!linkCont){
            linkCont = new Element('td', {className:'chart_links', style:'text-align: right;'});
            container.insert(linkCont);
        }else{
            linkCont.update('');
        }

        $A(["last", "next", "count", "previous", "first"]).each(function(relName){
            if(relName == "count"){
                var qData = this.getQueryByName(queryName);
                var input = new Element("input", {type:"text", value:qData["COUNT"], style:'width: 20px !important;height: 19px;text-align:right'});
                input.observe("keyup", function(e){
                    //if(e.keyCode == Event.KEY_RETURN){
                    if(e.keyCode == Event.KEY_UP) input.setValue(parseInt(input.getValue())+1);
                    else if(e.keyCode == Event.KEY_DOWN) input.setValue(parseInt(input.getValue())-1);
                    if(input.getValue()){
                        qData["COUNT"] = parseInt(input.getValue());
                        this.loadData(queryName, chart, 0, qData["COUNT"]);
                    }
                    //}
                }.bind(this));
                linkCont.insert(input);
                linkCont.insert('<span> '+this.defaultLinksUnits+' </span>');
                return;
            }
            var linkData = jsonData['links'].detect(function(l){
                return (l['rel'] == relName);
            });
            var a;
            if(linkData){
                a = this.createLink(queryName, linkData, chart);
            }else{
                a = this.createLink(queryName, relName, chart);
            }
            linkCont.insert(a);
        }.bind(this));
    },

    createLink: function(queryName, linkData, chart){
        var label;
        var rel;
        var linkActive = false;
        if(Object.isString(linkData)){
            rel = linkData;
        }else{
            linkActive = true;
            rel = linkData["rel"];
        }
        switch(rel){
            case "next":
                label = "icon-backward";
                break;
            case "previous":
                label = "icon-forward";
                break;
            case "last":
                label = "icon-fast-backward";
                break;
            case "first":
                label = "icon-fast-forward";
                break;
        }
        var link = new Element('a').update("<a class='"+label+"' style='display:inline-block; margin: 0 1px;"+(linkActive?"cursor:pointer;color:#399C9B;":"color:#CCCCCC;")+"'></a>");
        if(!Object.isString(linkData)){
            link.observe("click", function(){
                this.loadData(queryName, chart, linkData['cursor'], linkData['count']);
            }.bind(this));
        }
        return link;
    },

    updateTitle: function(label){
        this.element.down("span.header_label").update("<span class='icon-puzzle-piece'></span> " + label);
        this.element.fire("editor:updateTitle", "<span class='icon-puzzle-piece'></span> " + label);
    },

    /**
     * Resizes the main container
     * @param size int|null
     */
    resize : function(size){
        if(size){
            this.element.setStyle({height:size+'px'});
            //fitHeightToBottom(this.element);
        }else{
            fitHeightToBottom(this.element);
        }
        this.element.select('svg').each(function(chart){
            //chart.setStyle('width')
        });
        this.charts.each(function(pair){
            var queryName = pair.key;
            var chart = pair.value;
            var qData = this.getQueryByName(queryName);
            var qType = qData['DIAGRAM'];
            if(Prototype.Browser.Gecko && !Prototype.Browser.IE10plus && (!qType || qType == 'bar' || qType == 'area')){
                chart.setMargins(80, 20, 40, 145);
            }
            chart.draw(500);
            if(qData['LEGEND']){
                var el = this.element.down('#'+queryName+'_container');
                el.down('.dimple-legend-text').innerHTML = qData['LEGEND'];
            }

        }.bind(this));
        this.element.fire("editor:resize", size);
    },

    isDirty : function(){
        return false;
    }

});