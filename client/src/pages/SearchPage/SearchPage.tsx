import React, {useCallback, useEffect, useRef, useState} from 'react';
import _ from 'underscore';
import styles from './Search.module.scss';
import {SearchResponse, SelectedFields, Song, Suggestion} from '../../types';
import {fetchRelatedSuggestions, fetchSuggestions} from '../../data';
import ErrorBoundary from '../../components/ErrorBoundary';
import AcInput from './components/AcInput';
import {Heading} from '../../components/UI';
import MatchesPreview from './components/MatchesPreview';

const MIN_PREFIX_LENGTH = 2;
const DELAY_MS = 250;

const fields: string[] = ['artist_name', 'song_name', 'release_title'];
const defaultList = {
  artist_name: null,
  song_name: null,
  release_title: null,
};
const defaultValues = {
  artist_name: '',
  song_name: '',
  release_title: '',
};

const LABELS: { [field: string]: string } = {
  artist_name: 'Artist',
  song_name: 'Song',
  release_title: 'Release',
};

interface FieldsSearchResponse {
  [fields: string]: SearchResponse | null;
}

const debouncedFetch = _.debounce(fetchSuggestions, 200);

/**
 * The main data-entry view has few input fields, each of them provides:
 * - autocomplete suggestion support
 * - related suggestions support, i.e. request empty fields suggestions related to the filled fields
 */
export default function SearchPage() {
  const [typingResponses, setTyping] = useState<FieldsSearchResponse>(defaultList);
  const [inputValues, setInputValues] = useState<{ [field: string]: string }>(defaultValues);
  const [loadingFields, setLoading] = useState<{ [field: string]: boolean }>({
    artist_name: false,
    song_name: false,
    release_title: false,
  });
  const [relRes, setRelRes] = useState<FieldsSearchResponse>(defaultList);
  const [matchedItems, setMatchedItems] = useState<Song[]>([]);

  const [fieldsSelected, setSelected] = useState<SelectedFields>(defaultList);
  const currentTyped = useRef<string>('');

  const typingFetcher = useCallback(
      _.debounce((name: string, value: string, newSelected: any) => {
        fetchSuggestions(name, value, newSelected).then(res => {
          if (value !== currentTyped.current) {
            console.log(`Skipping old typing result for '${value}', current typed '${currentTyped.current}'`);
            return;
          }
          setLoading({...loadingFields, [name]: false});
          if ('error' in res) {
            throw new Error(res.error);
          }
          setTyping({...typingResponses, [name]: res});
        });
      }, DELAY_MS),
      [],
  );

  // When there are no typing responses, reset everything related.
  useEffect(() => {
    if (Object.values(typingResponses).filter(_ => _ !== null).length === 0) {
      setRelRes(defaultList);
      setSelected(defaultList);
      setMatchedItems([]);
    }
  }, [typingResponses]);

  /**
   * GET autocomplete suggestions for the field.
   */
  function typingHandler(name: string) {
    return (value: string) => {
      setInputValues({ ...inputValues, [name]: value });

      // Reset related suggestions and current selected field.
      const newSelected = { ...fieldsSelected, [name]: null };
      setSelected(newSelected);

      // Reset related responses too.
      setRelRes(defaultList);

      // Remember the most recent typed value to ignore delayed suggestions for previous values.
      currentTyped.current = value;

      if (!value || value.length < MIN_PREFIX_LENGTH) {
        setTyping({ ...typingResponses, [name]: null });

        return;
      }

      setLoading({ ...loadingFields, [name]: true });
      typingFetcher(name, value, newSelected);
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
      // Update the input value with a selected suggestion.
      setInputValues({ ...inputValues, [name]: suggestion.value });

      setLoading({ ...loadingFields, [name]: true });
      fetchRelatedSuggestions(empty, selected).then(res => {
        setLoading({ ...loadingFields, [name]: false });
        if ('error' in res) {
          throw new Error(res.error);
        }
        setMatchedItems(res.data);
        setRelRes({ ...relRes, ...res.fields });
      });
    };
  }

  return (
    <div className={styles.container}>
      <div className={styles.Panel}>
        <div className={styles.Form}>
          {fields.map((field: string) => (
            <ErrorBoundary key={field}>
              <AcInput
                name={field}
                value={inputValues[field]}
                selected={fieldsSelected[field]}
                response={typingResponses[field] || relRes[field]}
                placeholder={LABELS[field]}
                loading={loadingFields[field] || false}
                onTyping={typingHandler(field)}
                onSelect={selectionHandler(field)}
              />
            </ErrorBoundary>
          ))}

          <a
            href="#"
            onClick={e => {
              e.preventDefault();
              setTyping(defaultList);
              setInputValues(defaultValues);
            }}
            style={{ alignSelf: 'center' }}
          >
            Clear
          </a>
        </div>
      </div>

      <div className={styles.Preview}>
        <Heading h={3}>Data preview</Heading>

        {matchedItems.length > 0 && <MatchesPreview data={matchedItems} />}
      </div>
    </div>
  );
}
