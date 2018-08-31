NamespaceApplication.domLoaded(()=>{

  const App = new NamespaceApplication({
    path: '/',
    server: 'http://localhost/wdox/',
    node: {
      content: NamespaceApplication.query('#content'),
      template: NamespaceApplication.query('template#item'),
      buttonAdd: NamespaceApplication.query('#button-add'),
    }
  });

  const getItemTemplate = () =>
    document.importNode(document.importNode(App.node.template, true).content, true);

  const getItemNode = (target) =>
    target.parentNode.parentNode;

  const getItemId = (target) =>
    getItemNode(target).getAttribute('data-id');

  const getItemData = (target) =>
    App.query('.item-data', getItemNode(target));

  const getSelectionText = () => {
    let text = '';
    if (window.getSelection) {
      const selection = window.getSelection();
      text = selection.toString();
    }
    return text;
  };

  const api = (method, id, data, cb) => {
    App.httpRequest({data: {method: method, id: id, description: data}, action: App.server, method: 'POST',}, cb);
  };

  const addItem = () => {
    const item = getItemTemplate();

  };

  // add item
  App.on(App.node.buttonAdd, 'click', (e) => {
    App.node.content.appendChild(getItemTemplate());
  });

  App.on(App.node.content, 'mousedown', (e) => {
    const target = e.target;

    if (target.classList.contains('btn-save')) {

      api('save', getItemId(target), App.node2str(getItemData(target)), (code, response) => {
        console.log('response', code, response);
        const responseObject = JSON.parse(response);
        if (responseObject && responseObject.id) {
            getItemNode(target).setAttribute('data-id', responseObject.id);
        }
      });

    } else
    if (target.classList.contains('btn-remove')) {

      if (confirm("Are you sure ?")) {
        api('remove', getItemId(target), 0, (code, response) => {
          const responseObject = JSON.parse(response);
          if (responseObject.ok)
            getItemNode(target).parentNode.removeChild(getItemNode(target));
        });
      }

    } else
    if (target.classList.contains('btn-comment')) {

      console.log('selectionText', getSelectionText())

    }
  });


  api('get', 0, 0, (code, response) => {
    const savedItems = JSON.stringify(response);
  });


});
