<script>
const inventoryRecipeIngredients = {{ \Illuminate\Support\Js::from($inventoryIngredients->map(fn($i)=>[
    'id'=>$i->id,
    'name'=>$i->name,
    'base'=>$i->baseUnit?->symbol,
    'dimension'=>$i->measurement_dimension,
    'packages'=>$i->unitConversions->map(fn($c)=>['id'=>(int)$c->id,'label'=>$c->label ?: (($c->unit?->name ?? 'Package').' ('.rtrim(rtrim((string)$c->factor_to_base,'0'),'.').' '.$i->baseUnit?->symbol.')')])->values()
])->values()) }};
const inventoryRecipeUnits = {{ \Illuminate\Support\Js::from($inventoryUnits->where('dimension','!=','PACKAGE')->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'symbol'=>$u->symbol,'dimension'=>$u->dimension])->values()) }};
let inventoryRecipeIndex = document.querySelectorAll('.inventory-recipe-row').length;
function inventoryEsc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));}
function inventoryIngredientOptions(){return '<option value="">Select ingredient</option>'+inventoryRecipeIngredients.map(i=>`<option value="${i.id}">${inventoryEsc(i.name)} (${inventoryEsc(i.base)})</option>`).join('');}
function inventoryRecipeUnitOptions(ingredientId){
    const ingredient=inventoryRecipeIngredients.find(i=>String(i.id)===String(ingredientId));
    if(!ingredient)return '<option value="">Select ingredient first</option>';
    let html='<option value="">Select unit / package size</option>';
    html+=inventoryRecipeUnits.filter(u=>u.dimension===ingredient.dimension).map(u=>`<option value="u:${u.id}">${inventoryEsc(u.name)} (${inventoryEsc(u.symbol)})</option>`).join('');
    if(ingredient.packages.length){
        html+='<optgroup label="Package variants">'+ingredient.packages.map(p=>`<option value="c:${p.id}">${inventoryEsc(p.label)}</option>`).join('')+'</optgroup>';
    }
    return html;
}
function addRecipeRow(){const tbody=document.getElementById('inventoryRecipeRows');if(!tbody)return;const tr=document.createElement('tr');tr.className='inventory-recipe-row';tr.innerHTML=`<td><select name="recipe[${inventoryRecipeIndex}][ingredient_id]" class="progga-form-control recipe-ingredient" onchange="filterRecipeUnits(this)">${inventoryIngredientOptions()}</select></td><td><input type="number" step="0.01" min="0.01" name="recipe[${inventoryRecipeIndex}][quantity]" class="progga-form-control" placeholder="0.00"></td><td><select name="recipe[${inventoryRecipeIndex}][unit_choice]" class="progga-form-control recipe-unit"><option value="">Select ingredient first</option></select></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRecipeRow(this)"><i class="bi bi-trash"></i></button></td>`;tbody.appendChild(tr);inventoryRecipeIndex++;}
function removeRecipeRow(btn){const rows=document.querySelectorAll('.inventory-recipe-row');if(rows.length<=1){const row=btn.closest('.inventory-recipe-row');row.querySelector('.recipe-ingredient').value='';row.querySelector('input').value='';row.querySelector('.recipe-unit').innerHTML='<option value="">Select ingredient first</option>';return;}btn.closest('.inventory-recipe-row').remove();}
function filterRecipeUnits(ingredientSelect){const row=ingredientSelect.closest('.inventory-recipe-row');const unit=row.querySelector('.recipe-unit');const wanted=unit.dataset.selected||unit.value;unit.innerHTML=inventoryRecipeUnitOptions(ingredientSelect.value);if(wanted&&[...unit.options].some(o=>o.value===wanted))unit.value=wanted;unit.dataset.selected='';}
document.addEventListener('DOMContentLoaded',()=>document.querySelectorAll('.recipe-ingredient').forEach(filterRecipeUnits));
</script>
