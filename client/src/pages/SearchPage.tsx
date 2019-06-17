import React, { useRef, useState } from 'react';
import styles from './Search.module.scss';
import { RelatedResponse, SearchResponse, SelectedFields, Suggestion } from '../types';
import { fetchRelatedSuggestions, fetchSuggestions } from '../data';
import ErrorBoundary from '../components/ErrorBoundary';
import AcInput from '../components/AcInput/AcInput';
import { Heading } from "../components/UI";

const MIN_PREFIX_LENGTH = 2;

const fields: string[] = ['artist_name', 'song_name', 'release_title'];
const defaultList = {
  artist_name: null,
  song_name: null,
  release_title: null,
};

const LABELS: { [field: string]: string } = {
  artist_name: 'Artist',
  song_name: 'Song',
  release_title: 'Release',
};

interface FieldsSearchResponse {
  [fields: string]: SearchResponse | null;
}

/**
 * The main data-entry view has few input fields, each of them provides:
 * - autocomplete suggestion support
 * - related suggestions support, i.e. request empty fields suggestions related to the filled fields
 */
export default function SearchPage() {
  const [typingResponses, setTyping] = useState<FieldsSearchResponse>(defaultList);
  const [relatedResponse, setRelated] = useState<RelatedResponse | null>(null);

  const [fieldsSelected, setSelected] = useState<SelectedFields>(defaultList);
  const [activeField, setActiveField] = useState<string | null>();
  const currentTyped = useRef<string>('');

  /**
   * GET autocomplete suggestions for the field.
   */
  function typingHandler(name: string) {
    return (value: string) => {
      const newSelected = { ...fieldsSelected, [name]: null };
      // Reset related suggestions and current selected field.
      setRelated(null);
      setSelected(newSelected);
      currentTyped.current = value;

      if (!value || value.length < MIN_PREFIX_LENGTH) {
        setTyping({ ...typingResponses, [name]: null });

        return;
      }

      fetchSuggestions(name, value, newSelected).then(res => {
        if (value !== currentTyped.current) {
          console.log(`Skipping old typing result for '${value}', current typed '${currentTyped.current}'`);
          return;
        }
        if ('error' in res) {
          throw new Error(res.error);
        }
        setTyping({ ...typingResponses, [name]: res });
      });
    };
  }

  /**
   * GET related suggestions for empty fields based on the selected AC suggestions in filled fields.
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
        setRelated(res);
      });
    };
  }

  function renderAcInput(field: string) {
    const isActive = activeField === field;
    const selected = fieldsSelected[field];
    const typingResponse = typingResponses[field];
    // const relatedResponse = relatedResponses[field];
    // If field is already selected, don't provide "related" suggestions, keep "typing" to allow to select another one.
    const related = fieldsSelected[field] || !relatedResponse ? null : relatedResponse.fields[field];

    return (
      <AcInput
        name={field}
        isActive={isActive}
        selected={selected}
        typingResponse={typingResponse}
        relatedResponse={related}
        placeholder={LABELS[field]}
        onTyping={typingHandler(field)}
        onSelect={selectionHandler(field)}
        onFocus={() => setActiveField(field)}
      />
    );
  }

  return (
    <div className={styles.container}>
      <Panel>
        <div className={styles.Form}>
          {fields.map((field: string) => (
            <ErrorBoundary key={field}>{renderAcInput(field)}</ErrorBoundary>
          ))}
        </div>
      </Panel>

      <Preview>
        <Heading h={3}>Data preview</Heading>

        {relatedResponse && relatedResponse.data && <Metadata data={relatedResponse.data} />}
      </Preview>
    </div>
  );
}

function Panel({ children }: { children: any }) {
  return <div className={styles.Panel}>{children}</div>;
}

function Preview({ children }: { children: any }) {
  return <div className={styles.Preview}>{children}</div>;
}

// todo: each value in the list of grouped values is clickable. on click "choose" the corresponding item and fill the remaining AC fields
function Metadata({ data }: { data: any[] }) {
  const attrs = Object.keys(data[0]['_source']);

  const indexes = data.map(item => item._index);
  const ids = data.map(item => item._id);

  return (
    <div>
      <b>Index:</b> {indexes.join(', ')} <br />
      <b>Id:</b> {ids.join(', ')} <br />
      <h4>Values</h4>
      <div style={{ display: 'flex', flexWrap: 'wrap', flexDirection: 'column' }}>
        {attrs.map(f => (
          <div key={f}>
            <b>{f}:</b>

            {data
              .map(item => item['_source'][f])
              .filter(_ => !!_)
              .join(', ')}
          </div>
        ))}
      </div>
    </div>
  );
}
