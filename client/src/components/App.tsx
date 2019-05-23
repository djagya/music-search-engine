import React, { useState } from 'react';
import styles from './App.module.scss';
import { SearchResponse, SelectedFields, Suggestion } from '../types';
import { fetchRelatedSuggestions, fetchSuggestions, setUseAws } from '../data';
import ErrorBoundary from './ErrorBoundary';
import AcInput from './AcInput/AcInput';
import InstanceStatus from "./InstanceStatus";

const fields: string[] = ['artist', 'song', 'release', 'composer'];
const defaultList = {
  artist: null,
  song: null,
  release: null,
  composer: null,
};

interface FieldsSearchResponse {
  [fields: string]: SearchResponse | null;
}

function App() {
  // todo: new idea. maybe the list of relatedResponse suggestions should be just one, based on the current state of selected fields?
  const [typingResponses, setTyping] = useState<FieldsSearchResponse>(defaultList);
  const [relatedResponses, setRelated] = useState<FieldsSearchResponse>(defaultList);

  const [fieldsSelected, setSelected] = useState<SelectedFields>(defaultList);
  const [activeField, setActiveField] = useState<string | null>();

  /**
   * GET autocomplete suggestion for the field.
   * @param name
   */
  function typingHandler(name: string) {
    return (value: string) => {
      // todo: when there's some selected fields, send them in the request so we can filter on them like for relatedResponse suggestions
      if (value) {
        fetchSuggestions(name, value, fieldsSelected).then(res => {
          if ('error' in res) {
            throw new Error(res.error);
          }
          setTyping({ ...typingResponses, [name]: res });
        });
      } else {
        setTyping({ ...typingResponses, [name]: null });
      }
    };
  }

  /**
   * GET related items based on the selected inputs for all empty fields.
   * @param name
   */
  function selectionHandler(name: string) {
    return (suggestion: Suggestion) => {
      const selected = { ...fieldsSelected, [name]: suggestion };
      // What fields to return.
      const empty = fields.filter(f => !(f in selected) || !selected[f]);

      // Remember selected.
      setSelected(selected);
      setActiveField(null);

      return fetchRelatedSuggestions(empty, selected).then(res => {
        if ('error' in res) {
          throw new Error(res.error);
        }
        // todo: for now one list containing all fields and their suggestions is used, so temporary use 'name' = 'all'
        // setRelated({ ...relatedResponses, [name]: res });
        setRelated({ ...relatedResponses, all: res });
      });
    };
  }

  function renderAcInput(field: string) {
    const isActive = activeField === field;
    const selected = fieldsSelected[field];
    const typingResponse = typingResponses[field];
    // const relatedResponse = relatedResponses[field];
    // If field is already selected, don't provide "related" suggestions, keep "typing" to allow to select another one.
    const relatedResponse = fieldsSelected[field] ? null : relatedResponses['all'];

    return (
      <AcInput
        name={field}
        isActive={isActive}
        selected={selected}
        typingResponse={typingResponse}
        relatedResponse={relatedResponse}
        placeholder={field}
        onTyping={typingHandler(field)}
        onSelect={selectionHandler(field)}
        onFocus={() => setActiveField(field)}
      />
    );
  }

  return (
    <div className={styles.App}>
      <div className={styles.container}>
        <h1 className={styles.header}>Search</h1>

        <div className={styles.instance}>
          <span>AWS instance &nbsp;</span> <InstanceStatus onChange={(v) => setUseAws(v)} />
        </div>

        <div className={styles.Form}>
          {fields.map((field: string) => (
            <ErrorBoundary key={field}>{renderAcInput(field)}</ErrorBoundary>
          ))}
        </div>
      </div>
    </div>
  );
}

export default App;
