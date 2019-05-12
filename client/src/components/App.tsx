import React, { useState } from 'react';
import styles from './App.module.scss';
import AcInput from './AcInput/AcInput';
import { RelatedSuggestion, Suggestion } from '../types';
import { fetchRelatedSuggestions } from '../data';
import ErrorBoundary from './ErrorBoundary';

const fields: string[] = ['artist', 'song', 'release', 'composer'];
const defaultList = {
  artist: null,
  song: null,
  release: null,
  composer: null,
};

interface SelectedFields {
  [name: string]: Suggestion | null;
}

interface RelatedSuggestions {
  [name: string]: RelatedSuggestion[] | null;
}

function App() {
  const [fieldsSelected, setSelected] = useState<SelectedFields>(defaultList);
  const [relatedSuggestions, setRelated] = useState<RelatedSuggestions>(defaultList);
  const [activeField, setActiveField] = useState<string | null>();

  function selectionHandler(name: string) {
    return (suggestion: Suggestion) => {
      // Remember selected.
      const newSelected = { ...fieldsSelected, [name]: suggestion };
      setSelected(newSelected);

      // Search suggestions for other empty fields based on what was already selected in other fields.
      const selectedFields = fields.reduce((res: SelectedFields, k) => {
        if (newSelected[k]) {
          res[k] = newSelected[k];
        }
        return res;
      }, {});
      const emptyRelatedFields = fields.filter(f => f !== name && !fieldsSelected[f]);

      const promises = emptyRelatedFields.map((field: string) => fetchRelatedSuggestions(field, selectedFields));
      Promise.all(promises).then(results => {
        const related = emptyRelatedFields.reduce((res: RelatedSuggestions, field: string, k: number) => {
          res[field] = results[k];
          return res;
        }, {});
        setRelated(related);
      });
    };
  }

  return (
    <div className={styles.App}>
      <div className={styles.container}>
        <h1 className={styles.header}>Search</h1>

        <div className={styles.Form}>
          {fields.map((field: string) => (
            <ErrorBoundary key={field}>
              <AcInput
                name={field}
                active={activeField === field}
                selected={fieldsSelected[field]}
                related={relatedSuggestions[field]}
                placeholder={field}
                onFocus={() => setActiveField(field)}
                onSelect={selectionHandler(field)}
              />
            </ErrorBoundary>
          ))}
        </div>
      </div>
    </div>
  );
}

export default App;
