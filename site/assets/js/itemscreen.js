var itemscreen = function (name, options = {}) {
  this.name = name;
  this.options = {
    id: options.id || '',
    sess_token: options.sess_token || '',
    script_url: options.script_url || 'index.php?option=com_flexicontent&controller=items&tmpl=component'
      + (options.sess_token ? '&' + options.sess_token + '=1' : ''),
    task: options.task || ''
  };

  async function request(url, params) {
    const endpoint = new URL(url, document.baseURI);
    for (const [key, value] of Object.entries(params)) endpoint.searchParams.set(key, value);
    const response = await fetch(endpoint, {credentials: 'same-origin'});
    if (!response.ok) throw new Error('Error status: ' + response.status + ' , Error text: ' + response.statusText);
    return response.text();
  }

  async function update(targetName, url, params) {
    const target = document.getElementById(targetName);
    if (!target) return;
    const loader = document.createElement('img');
    loader.src = (window.jbase_url_fc || '') + 'components/com_flexicontent/assets/images/ajax-loader.gif';
    loader.alt = '';
    target.replaceChildren(loader);
    try {
      target.innerHTML = await request(url, params);
    } catch (error) {
      target.textContent = error.message;
    }
  }

  this.fetchscreen = function (targetName = this.name) {
    if (this.options.id <= 0) {
      const target = document.getElementById(targetName);
      if (target) target.textContent = '0';
      return;
    }
    return update(targetName, this.options.script_url, {format: 'raw', task: this.options.task, id: this.options.id});
  };

  this.addtag = async function (cid, tagname, url) {
    try {
      const [id, label] = (await request(url, {cid, name: tagname})).split('|');
      if (label === undefined) return;
      if (id !== '0') addToList(id, label);
      else alert(label);
    } catch (error) {
      alert('Failed to add tag');
    }
  };

  this.reseter = function (task, id, targetName, url) {
    return update(targetName ?? this.name, url, {format: 'raw', task, id});
  };
};
