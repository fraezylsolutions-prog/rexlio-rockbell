/* Part B: the keywords FOOD and DRINKS (also DRINK), typed in any case, list a whole
   kind - matched EXACTLY against item_kind, which the page derives from the drink
   flag. They replace VEG / BEV / BAR: BEV compared a spelling the data never had,
   BAR had no field at all, and VEG (upper case only) listed the drinks. */
function irSearchKind(nameKey){
    let k = String(nameKey || "").trim().toUpperCase();
    if(k === "FOOD"){ return "FOOD"; }
    if(k === "DRINKS" || k === "DRINK"){ return "DRINKS"; }
    return "";
}
function search(nameKey, myArray){
    let foundResult=new Array();
    let counter = 0;
    let kind = irSearchKind(nameKey);
    for (let i=0; i < myArray.length; i++) {
        // if (myArray[i].item_name === nameKey) {
        //     return myArray[i];
        // }
        /* a keyword is authoritative: FOOD / DRINKS list the kind and nothing else (an item filed
           under a category called "Drinks" but flagged not-a-drink is Food, as on the Food button) */
        if (kind !== "" ? (myArray[i].item_kind === kind) : (myArray[i].item_name.toLowerCase().includes(nameKey.toLowerCase()) || myArray[i].item_code.toLowerCase().includes(nameKey.toLowerCase()) || myArray[i].category_name.toLowerCase().includes(nameKey.toLowerCase()))) {
            foundResult.push(myArray[i]);
            counter++;
            if (nameKey && counter == 12) {
                break;
            }
        }
    }
    return foundResult.sort( function(a, b) {
      return parseInt(b.sold_for)-parseInt(a.sold_for);
    });
    //this is comment. it could be used if we want to sort this collection of object by item_name or anything else
    // return foundResult.sort( predicateBy("item_name") );
    
}
function getAlternativeNameById(menu_id,myArray){
    let name = '';
    for (let i=0; i < myArray.length; i++) {
        if (Number(myArray[i].item_id) === Number(menu_id)) {
            if(myArray[i].alternative_name){
                name = "("+myArray[i].alternative_name+")";
            }
        }
    }
    return name;
}

function searchAddress(nameKey, myArray){
    let foundResult=new Array();
    let counter = 0;
    for (let i=0; i < myArray.length; i++) {
        // if (myArray[i].item_name === nameKey) {
        //     return myArray[i];
        // }
        if (myArray[i].customer_id == nameKey) {
            foundResult.push(myArray[i]);
            counter++;
            if (nameKey && counter == 12) {
                break;
            }
        }
    }
    return foundResult;
    
}

function search_by_menu_id(menu_id,myArray){
    let foundResult=new Array();
    for (let i=0; i < myArray.length; i++) {
        if (Number(myArray[i].item_id) ===  Number(menu_id)) {
            foundResult.push(myArray[i]);
        }
    }
    return foundResult.sort();
}
function search_by_menu_id_getting_parent_id(menu_id,myArray){
    let parent_id = '';
    for (let i=0; i < myArray.length; i++) {
        if (Number(myArray[i].item_id) === Number(menu_id)) {
            parent_id = myArray[i].parent_id;
        }
    }
    return parent_id;
}
function get_variations_search_by_menu_id(menu_id,myArray){
    let foundResult=new Array();
    for (let i=0; i < myArray.length; i++) {
        if (Number(myArray[i].parent_id) === menu_id) {
            foundResult.push(myArray[i]);
        }
    }
    return foundResult.sort();
}

function predicateBy(prop){
   return function(a,b){
      if( a[prop] > b[prop]){
          return 1;
      }else if( a[prop] < b[prop] ){
          return -1;
      }
      return 0;
   }
}