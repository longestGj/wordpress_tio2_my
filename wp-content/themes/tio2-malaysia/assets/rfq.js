const rfqForm=document.getElementById('rfq-form');
const rfqConfigNode=document.getElementById('rfq-config');

if(rfqForm&&rfqConfigNode){
  const config=JSON.parse(rfqConfigNode.textContent);
  const summary=document.querySelector('.validation-summary');
  const summaryList=summary.querySelector('ul');
  const submit=rfqForm.querySelector('.rfq-submit');
  const state=document.querySelector('.rfq-state');
  const registered=new Set(['grade_id','application_id','quantity_mt','destination_country','destination_port_city','company_name','contact_name','business_email','phone_whatsapp','website','additional_requirements']);
  let pending=false;

  const value=name=>rfqForm.elements[name].value.trim();
  const length=text=>[...text].length;
  const clearErrors=()=>{
    summary.hidden=true;summaryList.replaceChildren();
    for(const name of registered){
      const field=rfqForm.elements[name];
      field.removeAttribute('aria-invalid');
      const error=document.getElementById(`rfq-field-${name}-error`);
      error.hidden=true;error.textContent='';
    }
  };
  const clientErrors=()=>{
    const errors={};const messages=config.errors;
    if(!value('grade_id'))errors.grade_id=messages.grade_required;
    if(!value('application_id'))errors.application_id=messages.application_required;
    const quantity=Number(value('quantity_mt'));
    if(value('quantity_mt')===''||!Number.isFinite(quantity)||quantity<=0)errors.quantity_mt=messages.quantity_invalid;
    const country=value('destination_country');
    if(!country)errors.destination_country=messages.destination_country_required;
    else if(length(country)>100)errors.destination_country=messages.destination_country_long;
    if(length(value('destination_port_city'))>120)errors.destination_port_city=messages.destination_port_city_long;
    const company=value('company_name');
    if(length(company)<2)errors.company_name=messages.company_name_required;
    else if(length(company)>160)errors.company_name=messages.company_name_long;
    const contact=value('contact_name');
    if(length(contact)<2)errors.contact_name=messages.contact_name_required;
    else if(length(contact)>100)errors.contact_name=messages.contact_name_long;
    const email=value('business_email');
    if(!email)errors.business_email=messages.business_email_required;
    else if(length(email)>254||!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email))errors.business_email=messages.business_email_invalid;
    if(length(value('phone_whatsapp'))>40)errors.phone_whatsapp=messages.phone_whatsapp_long;
    const website=value('website');
    if(website){
      let valid=false;
      try{const parsed=new URL(website);valid=['http:','https:'].includes(parsed.protocol)&&!!parsed.hostname&&!parsed.username&&!parsed.password;}catch{}
      if(length(website)>2048||!valid)errors.website=messages.website_invalid;
    }
    if(length(value('additional_requirements'))>2000)errors.additional_requirements=messages.additional_requirements_long;
    return errors;
  };
  const showErrors=raw=>{
    clearErrors();const errors={};
    for(const [name,message] of Object.entries(raw??{}))if(registered.has(name)&&typeof message==='string')errors[name]=message;
    if(!Object.keys(errors).length)return false;
    for(const [name,message] of Object.entries(errors)){
      const field=rfqForm.elements[name];const error=document.getElementById(`rfq-field-${name}-error`);
      field.setAttribute('aria-invalid','true');error.textContent=message;error.hidden=false;
      const item=document.createElement('li');const link=document.createElement('a');
      link.href=`#${field.id}`;link.textContent=message;item.append(link);summaryList.append(item);
    }
    summary.hidden=false;summary.focus();return true;
  };
  const resetPending=()=>{pending=false;submit.disabled=false;submit.textContent=config.normal;};
  const showState=(templateId,retry=false)=>{
    const template=document.getElementById(templateId);
    state.replaceChildren(template.content.cloneNode(true));state.hidden=false;state.focus();
    submit.hidden=retry;
    if(retry)state.querySelector('button').addEventListener('click',()=>{state.hidden=true;submit.hidden=false;rfqForm.requestSubmit(submit);},{once:true});
  };
  const submitRequest=async event=>{
    event.preventDefault();if(pending)return;
    state.hidden=true;submit.hidden=false;
    if(showErrors(clientErrors()))return;
    clearErrors();pending=true;submit.disabled=true;submit.textContent=config.pending;
    let response,payload;
    try{
      response=await fetch(rfqForm.getAttribute('action'),{method:'POST',body:new URLSearchParams(new FormData(rfqForm)),credentials:'same-origin',headers:{Accept:'application/json'}});
      payload=await response.json();
    }catch{}
    resetPending();
    if(payload?.state==='validation_failed'&&response?.status===422&&showErrors(payload.fieldErrors))return;
    if(payload?.state==='receipt_confirmed'&&response?.ok){
      rfqForm.reset();clearErrors();rfqForm.hidden=true;showState('rfq-success');return;
    }
    if(payload?.state==='service_unavailable'&&response?.status===503){showState('rfq-unavailable');return;}
    showState('rfq-failure',true);
  };
  rfqForm.addEventListener('submit',submitRequest);
}
