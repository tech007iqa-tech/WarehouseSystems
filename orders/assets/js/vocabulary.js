/**
 * IQA Intelligent Vocabulary Service
 * Dynamically populates datalists with historical brand/model/cpu data.
 */

const IQA_Vocabulary = {
    data: {
        brands: [],
        models: [],
        cpus: []
    },

    async init() {
        try {
            const response = await fetch('api/get_vocabulary.php');
            if (!response.ok) throw new Error('Vocabulary fetch failed');
            
            this.data = await response.json();
            this.populateAll();
            console.log("IQA Intelligence: Vocabulary Synced.");
        } catch (err) {
            console.warn("IQA Intelligence: Fallback to static inventory data.", err);
        }
    },

    populateAll() {
        this.populateList('brand-options', this.data.brands);
        this.populateList('model-options', this.data.models);
        this.populateList('cpu-options', this.data.cpus);
    },

    populateList(listId, items) {
        let list = document.getElementById(listId);
        if (!list) {
            // Create the list if it doesn't exist
            list = document.createElement('datalist');
            list.id = listId;
            document.body.appendChild(list);
        }

        // Clear existing (except static ones if needed, but we prefer fresh)
        list.innerHTML = '';
        
        items.sort().forEach(item => {
            const option = document.createElement('option');
            option.value = item;
            list.appendChild(option);
        });
    }
};

document.addEventListener('DOMContentLoaded', () => IQA_Vocabulary.init());
