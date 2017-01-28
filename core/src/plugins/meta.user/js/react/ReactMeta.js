(function(global){

    class Renderer{

        static getMetadataConfigs(){

            if(pydio && pydio.user && pydio.user.activeRepository && Renderer.__CACHE
                && Renderer.__CACHE.has(pydio.user.activeRepository)){
                return Renderer.__CACHE.get(pydio.user.activeRepository);
            }
            var configMap = new Map();
            try{
                let configs = JSON.parse(pydio.getPluginConfigs("meta.user").get("meta_definitions"));
                Object.keys(configs).map(function(key){
                    let value = configs[key];
                    var type = value.type;
                    if(type == 'choice' && value.data){
                        var values = new Map();
                        value.data.split(",").map(function(keyLabel){
                            var parts = keyLabel.split("|");
                            values.set(parts[0], parts[1]);
                        });
                        value.data = values;
                    }
                    configMap.set(key, value);
                });
            }catch(e){
                console.debug(e);
            }
            if(pydio && pydio.user && pydio.user.activeRepository){
                if(!Renderer.__CACHE) Renderer.__CACHE = new Map();
                Renderer.__CACHE.set(pydio.user.activeRepository, configMap);
            }
            return configMap;
        }

        static renderStars(node, column){
            return <MetaStarsRenderer node={node} column={column}/>;
        }

        static renderSelector(node, column){
            return <SelectorFilter node={node} column={column}/>;
        }

        static renderCSSLabel(node, column){
            return <CSSLabelsFilter node={node} column={column}/>;
        }

        static renderTagsCloud(node, column){
            return <TagsCloud node={node} column={column}/>;
        }


    }

    let MetaFieldRendererMixin = {

        propTypes:{
            node:React.PropTypes.instanceOf(AjxpNode),
            column:React.PropTypes.object
        },

        getRealValue: function(){
            return this.props.node.getMetadata().get(this.props.column.name);
        }

    };

    let MetaStarsRenderer = React.createClass({

        mixins:[MetaFieldRendererMixin],

        render: function(){
            let value = this.getRealValue() || 0;
            let stars = [0,1,2,3,4].map(function(v){
                return <span className={"mdi mdi-star" + (value > v ? '' : '-outline')}></span>;
            });
            return <span>{stars}</span>;
        }

    });

    let SelectorFilter = React.createClass({

        mixins:[MetaFieldRendererMixin],

        render: function(){
            let value;
            let displayValue = value = this.getRealValue();
            let configs = Renderer.getMetadataConfigs().get(this.props.column.name);
            if(configs && configs.data){
                displayValue = configs.data.get(value);
            }
            return <span>{displayValue}</span>;
        }

    });

    let CSSLabelsFilter = React.createClass({

        mixins:[MetaFieldRendererMixin],

        render: function(){
            let MessageHash = global.pydio.MessageHash;
            const data = {
                'low'       : {cssClass:'meta_low',         label:MessageHash['meta.user.4'], sortValue:'5'},
                'todo'      : {cssClass:'meta_todo',        label:MessageHash['meta.user.5'], sortValue:'4'},
                'personal'  : {cssClass:'meta_personal',    label:MessageHash['meta.user.6'], sortValue:'3'},
                'work'      : {cssClass:'meta_work',        label:MessageHash['meta.user.7'], sortValue:'2'},
                'important' : {cssClass:'meta_important',   label:MessageHash['meta.user.8'], sortValue:'1'}
            };

            let value = this.getRealValue();
            console.log(value);
            console.log(data);
            console.log(data[value]);
            if(value && data[value]){
                let dV = data[value];
                return <span className={dV.cssClass}>{dV.label}</span>
            }else{
                return <span>{value}</span>;
            }
        }

    });

    let TagsCloud = React.createClass({

        mixins:[MetaFieldRendererMixin],

        render: function(){
            let value = this.getRealValue() || "";
            let tags = value.split(",").map(function(tag){
                tag = LangUtils.trim(tag);
                if(!tag) return null;
                let removeTag = function(){
                    console.debug("Remove tag " + tag);
                };
                return <span className="meta_user_tag_block">{tag} <span className="mdi mdi-close" onClick={removeTag}></span></span>;
            });
            return <span>{tags}</span>;
        }

    });


    let ns = global.ReactMeta || {};
    ns.Renderer = Renderer;
    global.ReactMeta = ns;

})(window);