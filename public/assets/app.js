async function apiGet(url){
  const res = await fetch(url, {headers: {'Accept':'application/json'}});
  if(!res.ok){
    let text='';
    try { text = await res.text(); } catch(e) {}
    throw new Error('Request failed: ' + res.status + ' ' + text);
  }
  return res.json();
}

function initPasswordToggles(){
  const toggles = document.querySelectorAll('.pw-toggle[data-target]');
  if(!toggles || toggles.length === 0) return;

  toggles.forEach(btn => {
    const id = btn.getAttribute('data-target');
    const input = id ? document.getElementById(id) : null;
    if(!input) return;

    btn.addEventListener('click', () => {
      const isPw = input.getAttribute('type') === 'password';
      input.setAttribute('type', isPw ? 'text' : 'password');
      btn.classList.toggle('is-on', isPw);
      btn.setAttribute('aria-pressed', isPw ? 'true' : 'false');
    });
  });
}

function getSelected(sel){
  const v = sel && sel.dataset ? sel.dataset.selected : '';
  return v ? String(v) : '';
}

function clearSelect(sel, placeholder){
  sel.innerHTML='';
  const opt=document.createElement('option');
  opt.value='';
  opt.textContent=placeholder;
  sel.appendChild(opt);
  sel.value='';
}

function setDisabled(sel, disabled){
  sel.disabled = disabled;
  sel.classList.toggle('is-disabled', disabled);
}

function fillSelect(sel, items, placeholder){
  clearSelect(sel, placeholder);
  for(const item of items){
    const opt=document.createElement('option');
    opt.value=String(item.id);
    opt.textContent=item.name;
    sel.appendChild(opt);
  }
}

function fillDevices(sel, items){
  clearSelect(sel, 'Select device');
  for(const item of items){
    const opt=document.createElement('option');
    opt.value=String(item.id);
    const tag = item.asset_tag ? (' · ' + item.asset_tag) : '';
    const label = item.label ? (' — ' + item.label) : '';
    opt.textContent=item.device_type + tag + label;
    sel.appendChild(opt);
  }
}

async function initIssueForm(){
  const campus=document.querySelector('#campus_id');
  const building=document.querySelector('#building_id');
  const room=document.querySelector('#room_id');
  const device=document.querySelector('#device_id');
  const submitBtn=document.querySelector('#submitBtn');

  if(!campus) return;

  clearSelect(campus, 'Loading campuses...');
  clearSelect(building, 'Select building');
  clearSelect(room, 'Select room');
  clearSelect(device, 'Select device');

  setDisabled(building, true);
  setDisabled(room, true);
  setDisabled(device, true);
  submitBtn && (submitBtn.disabled = true);

  const selectedCampus = getSelected(campus);
  const selectedBuilding = getSelected(building);
  const selectedRoom = getSelected(room);
  const selectedDevice = getSelected(device);

  async function loadBuildings(campusId){
    clearSelect(building, 'Loading buildings...');
    clearSelect(room, 'Select room');
    clearSelect(device, 'Select device');
    setDisabled(building, true);
    setDisabled(room, true);
    setDisabled(device, true);
    submitBtn && (submitBtn.disabled = true);

    if(!campusId){
      clearSelect(building, 'Select building');
      return;
    }

    try{
      const buildings = await apiGet('/api/buildings?campus_id=' + encodeURIComponent(campusId));
      fillSelect(building, buildings.data, 'Select building');
      setDisabled(building, false);
    } catch(e){
      clearSelect(building, 'Failed to load buildings');
    }
  }

  async function loadRooms(buildingId){
    clearSelect(room, 'Loading rooms...');
    clearSelect(device, 'Select device');
    setDisabled(room, true);
    setDisabled(device, true);
    submitBtn && (submitBtn.disabled = true);

    if(!buildingId){
      clearSelect(room, 'Select room');
      return;
    }

    try{
      const rooms = await apiGet('/api/rooms?building_id=' + encodeURIComponent(buildingId));
      fillSelect(room, rooms.data, 'Select room');
      setDisabled(room, false);
    } catch(e){
      clearSelect(room, 'Failed to load rooms');
    }
  }

  async function loadDevices(roomId){
    clearSelect(device, 'Loading devices...');
    setDisabled(device, true);
    submitBtn && (submitBtn.disabled = true);

    if(!roomId){
      clearSelect(device, 'Select device');
      return;
    }

    try{
      const devices = await apiGet('/api/devices?room_id=' + encodeURIComponent(roomId));
      fillDevices(device, devices.data);
      setDisabled(device, false);
    } catch(e){
      clearSelect(device, 'Failed to load devices');
    }
  }

  try{
    const campuses = await apiGet('/api/campuses');
    fillSelect(campus, campuses.data, 'Select campus');

    if(selectedCampus){
      campus.value = selectedCampus;
      await loadBuildings(selectedCampus);
      if(selectedBuilding){
        building.value = selectedBuilding;
        await loadRooms(selectedBuilding);
        if(selectedRoom){
          room.value = selectedRoom;
          await loadDevices(selectedRoom);
          if(selectedDevice){
            device.value = selectedDevice;
            submitBtn && (submitBtn.disabled = !device.value);
          }
        }
      }
    }
  } catch(e){
    clearSelect(campus, 'Failed to load campuses');
  }

  campus.addEventListener('change', async () => {
    await loadBuildings(campus.value);
  });

  building.addEventListener('change', async () => {
    await loadRooms(building.value);
  });

  room.addEventListener('change', async () => {
    await loadDevices(room.value);
  });

  device.addEventListener('change', () => {
    if(submitBtn){
      submitBtn.disabled = !device.value;
    }
  });
}

document.addEventListener('DOMContentLoaded', initIssueForm);
document.addEventListener('DOMContentLoaded', initPasswordToggles);
